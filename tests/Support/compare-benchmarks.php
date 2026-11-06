<?php

declare(strict_types=1);

require_once __DIR__.'/BenchmarkComparison.php';

use HelgeSverre\Toon\Tests\Support\BenchmarkComparison;

try {
    if (count($argv) !== 4 && count($argv) !== 5) {
        throw new RuntimeException('Usage: php compare-benchmarks.php BASE.xml CANDIDATE.xml REPORT.md [EXPECTED_WORKLOADS]');
    }
    $baseline = BenchmarkComparison::read($argv[1]);
    $candidate = BenchmarkComparison::read($argv[2]);
    $expected = $argv[4] ?? null;
    if ($expected !== null && (preg_match('/^[1-9][0-9]*$/D', $expected) !== 1 || count($baseline) !== (int) $expected)) {
        throw new RuntimeException('Unexpected number of benchmark workloads');
    }
    $result = BenchmarkComparison::compare($baseline, $candidate);
    if (file_put_contents($argv[3], $result['markdown']) === false) {
        throw new RuntimeException('Cannot write benchmark report');
    }
    echo $result['markdown'];
    exit($result['regression'] ? 1 : 0);
} catch (Throwable $error) {
    $message = 'Benchmark comparison failed: '.$error->getMessage();
    if (is_array($argv) && array_key_exists(3, $argv)) {
        file_put_contents($argv[3], "## Performance comparison\n\n".$message."\n");
    }
    fwrite(STDERR, $message."\n");
    exit(2);
}
