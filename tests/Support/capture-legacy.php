<?php

declare(strict_types=1);

// Regenerate only against the pinned release source, never the current implementation.
// php tests/Support/capture-legacy.php /path/to/release-checkout
$source = $argv[1] ?? throw new InvalidArgumentException('Supply the v3.2.1 source checkout');
$manifest = json_decode(file_get_contents(__DIR__.'/../Fixtures/legacy-source.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($manifest['sha256'] as $file => $hash) {
    if (! is_file($source.'/'.$file) || hash_file('sha256', $source.'/'.$file) !== $hash) {
        throw new RuntimeException('Source does not match the pinned release: '.$file);
    }
}
spl_autoload_register(static function (string $class) use ($source): void {
    $prefix = 'HelgeSverre\\Toon\\';
    if (str_starts_with($class, $prefix)) {
        require $source.'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
    }
});
$results = [];
foreach (['encode', 'decode'] as $operation) {
    foreach (glob(__DIR__.'/../Fixtures/spec/3.3/'.$operation.'/*.json') as $file) {
        $fixture = json_decode(file_get_contents($file), false, 512, JSON_THROW_ON_ERROR);
        foreach ($fixture->tests as $index => $case) {
            $options = $case->options ?? new stdClass;
            try {
                $value = $operation === 'encode'
                    ? HelgeSverre\Toon\Toon::encode($case->input, new HelgeSverre\Toon\EncodeOptions(indent: $options->indent ?? $options->indentSize ?? 2, delimiter: $options->delimiter ?? ','))
                    : HelgeSverre\Toon\Toon::decode($case->input, new HelgeSverre\Toon\DecodeOptions(indent: $options->indent ?? $options->indentSize ?? 2, strict: $options->strict ?? true));
                $result = ['value' => $value];
            } catch (Throwable $error) {
                $result = ['error' => $error::class, 'message' => $error->getMessage()];
            }
            $results[$operation.'/'.basename($file).'#'.$index] = $result;
        }
    }
}
echo json_encode(['release' => '3.2.1', 'revision' => '55ca6ba93846bde75eeb04dac70a99fe0bb435dd', 'cases' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)."\n";
