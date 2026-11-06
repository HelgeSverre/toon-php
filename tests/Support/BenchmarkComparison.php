<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests\Support;

use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use RuntimeException;

final class BenchmarkComparison
{
    /** @return array<string, float> Median microseconds per revolution, keyed by workload. */
    public static function read(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Missing benchmark results: '.$path);
        }
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $document->preserveWhiteSpace = false;
        try {
            if (! $document->load($path, LIBXML_NONET) || $document->doctype !== null) {
                throw new RuntimeException('Invalid benchmark XML: '.$path);
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        if ($xpath->evaluate('count(/phpbench/suite)') !== 1.0 || $xpath->evaluate('count(//error | //failure)') !== 0.0) {
            throw new RuntimeException('Expected one successful benchmark suite: '.$path);
        }
        $variants = $xpath->query('/phpbench/suite/benchmark/subject/variant');
        if (! $variants instanceof DOMNodeList || $variants->length === 0) {
            throw new RuntimeException('No benchmark measurements: '.$path);
        }
        $results = [];
        foreach ($variants as $variant) {
            if (! $variant instanceof DOMElement) {
                throw new RuntimeException('Invalid benchmark structure');
            }
            $subject = $variant->parentNode;
            if (! $subject instanceof DOMElement) {
                throw new RuntimeException('Missing benchmark subject');
            }
            $benchmark = $subject->parentNode;
            if (! $benchmark instanceof DOMElement) {
                throw new RuntimeException('Missing benchmark class');
            }
            $parameters = $xpath->query('parameter-set', $variant);
            if (! $parameters instanceof DOMNodeList || $parameters->length !== 1 || $benchmark->getAttribute('class') === '' || $subject->getAttribute('name') === '') {
                throw new RuntimeException('Missing workload identity');
            }
            $parameterSet = $parameters->item(0);
            if (! $parameterSet instanceof DOMElement) {
                throw new RuntimeException('Missing parameter set');
            }
            $canonicalParameters = $parameterSet->C14N();
            if ($canonicalParameters === false) {
                throw new RuntimeException('Cannot canonicalize parameter set');
            }
            $key = $benchmark->getAttribute('class').'::'.$subject->getAttribute('name').' ['.$parameterSet->getAttribute('name').':'.substr(hash('sha256', $canonicalParameters), 0, 12).']';
            if (array_key_exists($key, $results)) {
                throw new RuntimeException('Duplicate benchmark workload: '.$key);
            }
            $iterations = $xpath->query('iteration', $variant);
            if (! $iterations instanceof DOMNodeList || $iterations->length < 3) {
                throw new RuntimeException('Need at least three iterations per workload');
            }
            $times = [];
            foreach ($iterations as $iteration) {
                if (! $iteration instanceof DOMElement) {
                    throw new RuntimeException('Invalid iteration');
                }
                $average = $iteration->getAttribute('time-avg');
                if (! is_numeric($average)) {
                    throw new RuntimeException('Missing numeric time-avg');
                }
                $time = (float) $average;
                if (! is_finite($time) || $time <= 0.0) {
                    throw new RuntimeException('Benchmark time must be finite and positive');
                }
                $times[] = $time;
            }
            sort($times);
            $count = count($times);
            $results[$key] = ($times[intdiv($count - 1, 2)] + $times[intdiv($count, 2)]) / 2;
        }
        ksort($results);

        return $results;
    }

    /**
     * @param array<string, float> $baseline
     * @param array<string, float> $candidate
     * @return array{markdown: string, regression: bool}
     */
    public static function compare(array $baseline, array $candidate, float $threshold = 15.0): array
    {
        if (! is_finite($threshold) || $threshold < 0.0 || $baseline === []) {
            throw new RuntimeException('Invalid threshold or empty baseline');
        }
        ksort($baseline);
        ksort($candidate);
        if (array_keys($baseline) !== array_keys($candidate)) {
            throw new RuntimeException('Baseline and candidate workloads differ; comparison would be incomplete');
        }
        $regression = false;
        $lines = [
            '<!-- toon-benchmark-comparison -->',
            '## Performance comparison',
            '',
            'Median microseconds per revolution. Slowdowns greater than '.$threshold.'% fail the check.',
            '',
            '| Workload | Base (µs) | Candidate (µs) | Change | Result |',
            '| --- | ---: | ---: | ---: | --- |',
        ];
        foreach ($baseline as $key => $before) {
            $after = $candidate[$key];
            if (! is_finite($before) || ! is_finite($after) || $before <= 0.0 || $after <= 0.0) {
                throw new RuntimeException('Comparison times must be finite and positive');
            }
            $change = (($after / $before) - 1.0) * 100.0;
            $failed = $change > $threshold + 1e-9;
            $regression = $regression || $failed;
            $label = str_replace(['|', '`', "\n", "\r", '@'], ['\\|', '', ' ', ' ', '&#64;'], $key);
            $lines[] = sprintf('| `%s` | %.3f | %.3f | %+.2f%% | %s |', $label, $before, $after, $change, $failed ? 'REGRESSION' : 'pass');
        }

        return ['markdown' => implode("\n", $lines)."\n", 'regression' => $regression];
    }
}
