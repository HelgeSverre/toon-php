#!/usr/bin/env ruby
# Reproducible local experiment. Deliberate PHP defects exist only while this runs.
require 'json'
require 'open3'
require 'fileutils'
require 'tmpdir'

root = File.expand_path('../..', __dir__)
baseline = ENV.fetch('TOON_BASELINE', File.join(File.dirname(root), 'toon-php'))
php = ENV.fetch('TOON_BENCH_PHP', 'php')
runs = Integer(ENV.fetch('TOON_BENCH_RUNS', '10'))
data = File.join(root, 'docs/experiments/mago-data')
FileUtils.mkdir_p(data)
cache = Dir.mktmpdir('toon-mago-benchmark-')
probe = File.join(root, 'tests/Support/ToolingProbes')
raise 'Probe directory already exists' if File.exist?(probe)
pint = [php, File.join(baseline, 'vendor/laravel/pint/builds/pint')]
stan = [php, File.join(baseline, 'vendor/phpstan/phpstan/phpstan.phar')]
mago = [php, File.join(root, 'vendor/bin/mago')]
env = {'NO_COLOR' => '1', 'TERM' => 'dumb'}

invoke = lambda do |cmd, cwd = root|
  start = Process.clock_gettime(Process::CLOCK_MONOTONIC)
  out, err, status = Open3.capture3(env, *cmd, chdir: cwd)
  {seconds: Process.clock_gettime(Process::CLOCK_MONOTONIC) - start,
   exit: status.exitstatus, stdout: out, stderr: err}
end

make_stan = lambda do |name, paths, tmp|
  path = File.join(cache, name + '.neon')
  File.write(path, "parameters:\n    level: 9\n    tmpDir: #{tmp}\n    paths:\n" + paths.map { |p| "        - #{p}\n" }.join)
  path
end

begin
  if ENV['TOON_BENCH_MODE'] == 'paired'
    config = make_stan.call('paired', [File.join(baseline, 'src')], File.join(cache, 'paired-stan'))
    workflows = {
      baseline: [
        [pint + ['src', 'tests', '--test', "--config=#{File.join(baseline, 'pint.json')}", "--cache-file=#{File.join(cache, 'paired-pint.json')}"], baseline],
        [stan + ['analyse', '-c', config, '--no-progress', '--error-format=json'], baseline],
      ],
      mago: [
        [mago + ['format', '--check'], root],
        [mago + ['lint', '--reporting-format=json', '--minimum-report-level=warning'], root],
        [mago + ['analyze', '--reporting-format=json', '--minimum-report-level=warning'], root],
      ],
    }
    2.times { workflows.each_value { |commands| commands.each { |cmd, cwd| invoke.call(cmd, cwd) } } }
    samples = []
    runs.times do |i|
      row = {}
      order = i.even? ? workflows.keys : workflows.keys.reverse
      order.each do |tool|
        start = Process.clock_gettime(Process::CLOCK_MONOTONIC)
        statuses = workflows.fetch(tool).map { |cmd, cwd| invoke.call(cmd, cwd)[:exit] }
        row[tool] = {seconds: Process.clock_gettime(Process::CLOCK_MONOTONIC) - start, exits: statuses}
      end
      samples << row
    end
    File.write(File.join(data, 'paired-warm.json'), JSON.pretty_generate({samples: samples, configuration: File.read(File.join(root, 'mago.toml')), php: invoke.call([php, '-v'])[:stdout].lines.first.strip}) + "\n")
    puts JSON.pretty_generate(samples)
    exit
  end
  format_cache = File.join(cache, 'pint.json')
  stan_config = make_stan.call('src', [File.join(root, 'src')], File.join(cache, 'stan'))
  cases = {
    'pint_cold' => lambda { |i| [pint + ['src', 'tests', '--test', "--config=#{File.join(baseline, 'pint.json')}", "--cache-file=#{File.join(cache, "pint-#{i}.json")}"], root] },
    'pint_warm' => lambda { |_| [pint + ['src', 'tests', '--test', "--config=#{File.join(baseline, 'pint.json')}", "--cache-file=#{format_cache}"], root] },
    'phpstan_cold_src' => lambda { |i|
      config = make_stan.call("cold-#{i}", [File.join(root, 'src')], File.join(cache, "stan-#{i}"))
      [stan + ['analyse', '-c', config, '--no-progress', '--error-format=json', '--memory-limit=1G'], baseline]
    },
    'phpstan_warm_src' => lambda { |_| [stan + ['analyse', '-c', stan_config, '--no-progress', '--error-format=json', '--memory-limit=1G'], baseline] },
    'mago_format' => lambda { |_| [mago + ['format', '--check'], root] },
    'mago_lint' => lambda { |_| [mago + ['lint', '--reporting-format=json', '--minimum-report-level=warning'], root] },
    'mago_analyze_src' => lambda { |_| [mago + ['analyze', 'src', '--reporting-format=json', '--minimum-report-level=warning'], root] },
    'mago_analyze_all' => lambda { |_| [mago + ['analyze', '--reporting-format=json', '--minimum-report-level=warning'], root] },
  }
  version = invoke.call(mago + ['--version'])[:stdout].strip
  result_file = File.join(data, 'results-' + version.split.last + '.json')
  results = {base_commit: invoke.call(['git', '-C', baseline, 'rev-parse', 'HEAD'], baseline)[:stdout].strip, mago: version, php: invoke.call([php, '-v'])[:stdout].lines.first.strip, runs: runs, timing: {}}
  results = JSON.parse(File.read(result_file), symbolize_names: true) if (ENV['TOON_BENCH_SKIP_TIMING'] == '1' || ENV['TOON_BENCH_TOOLS'] == 'mago') && File.file?(result_file)
  results[:timing_configuration] = File.read(File.join(root, 'mago.toml')) unless ENV['TOON_BENCH_SKIP_TIMING'] == '1'
  cases.each do |name, build|
    next if ENV['TOON_BENCH_SKIP_TIMING'] == '1'
    next if ENV['TOON_BENCH_TOOLS'] == 'mago' && !name.start_with?('mago_')
    invoke.call(*build.call(-1)) # warm binaries and OS file cache; tool-cache cold cases use new paths
    samples = []
    runs.times do |i|
      result = invoke.call(*build.call(i))
      allowed = name.start_with?('mago_') ? [0, 1] : [0]
      raise "Tool execution failed: #{name}: #{result[:stderr]}" unless allowed.include?(result[:exit])
      samples << result.slice(:seconds, :exit)
      if i.zero?
        File.write(File.join(data, name + '.stdout'), result[:stdout])
        File.write(File.join(data, name + '.stderr'), result[:stderr])
      end
    end
    times = samples.map { |v| v[:seconds] }.sort
    mean = times.sum / times.size
    results[:timing][name] = {mean: mean, median: (times[(times.size - 1) / 2] + times[times.size / 2]) / 2, min: times.first, max: times.last, samples: samples}
    File.write(result_file, JSON.pretty_generate(results) + "\n")
    puts "#{name}: #{(mean * 1000).round(1)} ms; exit #{samples.map { |v| v[:exit] }.uniq.join(',')}"
    STDOUT.flush
  end

  exit if ENV['TOON_BENCH_SKIP_PROBES'] == '1'

  # Isolated detection probes: analyze exactly this file, with project/vendor type information.
  FileUtils.mkdir_p(probe)
  fixture = File.join(probe, 'Probe.php')
  # Exercise the corrected candidate's actual analyzer policy.
  config = File.read(File.join(root, 'mago.toml'))
               .sub('paths = ["src", "tests"]', 'paths = ["tests/Support/ToolingProbes/Probe.php"]')
               .sub(/^includes = .*$/, 'includes = ["src", "vendor", "tests/Fixtures/mate"]')
  probe_mago = File.join(cache, 'probe.toml')
  File.write(probe_mago, config)
  results[:probe_configuration] = config
  probes = {
    'wrong_return' => ['bug', 'function value(): int { return "oops"; }'],
    'undefined_method' => ['bug', 'function value(\\HelgeSverre\\Toon\\EncodeOptions $o): void { $o->withStrict(true); }'],
    'nullable_property' => ['bug', 'final class Item { public string $name = "x"; } function value(?Item $v): int { return strlen($v->name); }'],
    'missing_shape_key' => ['bug', "/** @param array{id:int} $row */\nfunction value(array $row): int { return $row['missing']; }"],
    'not_a_list' => ['bug', "/** @return list<int> */\nfunction value(): array { return ['id' => 1]; }"],
    'unsafe_mixed_return' => ['bug', 'function value(mixed $v): int { return $v; }'],
    'non_exhaustive_version' => ['bug', 'function value(\\HelgeSverre\\Toon\\SpecVersion $v): string { return match ($v) { \\HelgeSverre\\Toon\\SpecVersion::V3_3 => "old", \\HelgeSverre\\Toon\\SpecVersion::V4_1 => "new" }; }'],
    'missing_array_value_type' => ['type-contract', 'function value(array $v): array { return $v; }'],
    'php82_readonly_class' => ['compatibility', 'readonly class Item { public function __construct(public int $n) {} }'],
    'valid_refinement' => ['valid', 'function value(mixed $v): int { if (!is_int($v)) { throw new \\InvalidArgumentException(); } return $v; }'],
    'valid_list_projection' => ['valid', "/**\n * @param array<array-key,int> $v\n * @return list<int>\n */\nfunction value(array $v): array { return array_values($v); }"],
    'valid_generic' => ['valid', "/**\n * @template T\n * @param T $v\n * @return T\n */\nfunction value(mixed $v): mixed { return $v; }"],
    'valid_regex_guard' => ['valid', "/**\n * @param list<string> $lines\n * @return list<string>\n */\nfunction value(array $lines, bool $strict): array {\n $result = [];\n foreach ($lines as $line) {\n  if (!$strict && preg_match('/^[ \\t]+/', $line, $prefix) === 1) {\n   $line = $prefix[0].substr($line, strlen($prefix[0]));\n  }\n  $result[] = $line;\n }\n return $result;\n}"],
  }
  results[:probes] = {}
  probes.each do |name, (kind, body)|
    probe_stan = make_stan.call('probe-' + name, [fixture], File.join(cache, 'stan-probes-' + name))
    File.write(fixture, "<?php\ndeclare(strict_types=1);\nnamespace ToolingExperiment;\n#{body}\n")
    result = {kind: kind}
    if kind == 'valid'
      expression = {
        'valid_refinement' => '\\ToolingExperiment\\value(1) === 1',
        'valid_list_projection' => '\\ToolingExperiment\\value(["a" => 1]) === [1]',
        'valid_generic' => '\\ToolingExperiment\\value("x") === "x"',
        'valid_regex_guard' => '\\ToolingExperiment\\value(["  x", "y", "\\tz"], false) === ["  x", "y", "\\tz"] && \\ToolingExperiment\\value(["  x", "y"], true) === ["  x", "y"]',
      }.fetch(name)
      driver = 'set_error_handler(static function($s,$m,$f,$l) { throw new \\ErrorException($m,0,$s,$f,$l); }); require $argv[1]; if (!('+expression+')) { throw new \\RuntimeException("Valid control failed"); }'
      run = invoke.call([php, '-r', driver, fixture])
      result[:runtime] = run.slice(:exit, :stderr)
      raise "Invalid runtime control #{name}: #{run[:stderr]}" unless run[:exit] == 0
    end
    {
      phpstan: [stan + ['analyse', '-c', probe_stan, '--no-progress', '--error-format=json'], baseline],
      mago: [mago + ['--workspace', root, '--config', probe_mago, 'analyze', '--reporting-format=json', '--minimum-report-level=warning'], root],
    }.each do |tool, (cmd, cwd)|
      run = invoke.call(cmd, cwd)
      parsed = JSON.parse(run[:stdout]) rescue nil
      raise "#{tool} returned no JSON for #{name}: #{run[:stderr]}" unless parsed
      raise "PHPStan infrastructure error: #{parsed['errors']}" if tool == :phpstan && !parsed.fetch('errors', []).empty?
      diagnostics = if tool == :mago
        parsed && parsed['issues']&.map { |v| {code: v['code'], message: v['message']} }
      else
        files = parsed && parsed.fetch('files', {})
        parsed && (files.is_a?(Hash) ? files.values : files).flat_map { |v| v.fetch('messages', []) }.map { |v| {code: v['identifier'], message: v['message']} }
      end
      result[tool] = {exit: run[:exit], diagnostics: diagnostics, stderr: run[:stderr]}
    end
    if name == 'php82_readonly_class'
      config81 = make_stan.call('php81-probe', [fixture], File.join(cache, 'stan-php81'))
      File.open(config81, 'a') { |f| f.write("    phpVersion: 80100\n") }
      run = invoke.call(stan + ['analyse', '-c', config81, '--no-progress', '--error-format=json'], baseline)
      result[:phpstan_php81] = {exit: run[:exit], output: JSON.parse(run[:stdout])}
    elsif name == 'missing_array_value_type'
      strict_config = File.join(cache, 'strict-probe.toml')
      File.write(strict_config, config.include?('check-missing-type-hints = true') ? config : config.sub("[analyzer]\n", "[analyzer]\ncheck-missing-type-hints = true\n"))
      run = invoke.call(mago + ['--workspace', root, '--config', strict_config, 'analyze', '--reporting-format=json', '--minimum-report-level=warning'], root)
      result[:mago_missing_hint_check] = {exit: run[:exit], output: JSON.parse(run[:stdout])}
    end
    results[:probes][name] = result
    File.write(result_file, JSON.pretty_generate(results) + "\n")
    puts "#{name}: PHPStan=#{result[:phpstan][:exit]}, Mago=#{result[:mago][:exit]}"
    STDOUT.flush
  end
  File.write(result_file, JSON.pretty_generate(results) + "\n")
ensure
  FileUtils.remove_entry(probe) if File.directory?(probe)
  FileUtils.remove_entry(cache) if File.directory?(cache)
end
