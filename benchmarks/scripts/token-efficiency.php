#!/usr/bin/env php
<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../../vendor/autoload.php';

use Benchmarks\Counters;
use Benchmarks\Datasets;
use Benchmarks\Formatters;
use Benchmarks\Report;

// Load environment variables from .env if it exists.
if (file_exists(__DIR__.'/../.env')) {
    $lines = file(__DIR__.'/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            putenv(trim($key).'='.trim($value));
        }
    }
}

echo "TOON Token Efficiency Benchmark\n\n";

$datasets = new Datasets;

// One counter per available tokenizer. Local counters (estimate, tiktoken) always
// run; hosted ones (Anthropic, Gemini, OpenAI) run only when their API key is set.
echo "Counters:\n";
$counters = Counters::build();
$labels = array_map(static fn ($counter) => $counter->label, $counters);
foreach ($labels as $label) {
    echo "  ✓ {$label}\n";
}
echo "\n";

$report = new Report($labels);

$benchmarks = [
    [
        'name' => 'GitHub Repositories',
        'description' => 'Top 100 GitHub repositories with stars, forks, and metadata',
        'data' => fn () => $datasets->generateRepositories(100),
    ],
    [
        'name' => 'Analytics Data',
        'description' => '180 days of web metrics (views, clicks, conversions, revenue)',
        'data' => fn () => $datasets->generateAnalytics(180),
    ],
    [
        'name' => 'E-Commerce Orders',
        'description' => '50 nested orders with customer and item details',
        'data' => fn () => $datasets->generateOrders(50),
    ],
    [
        'name' => 'Employee Records',
        'description' => '100 tabular employee records',
        'data' => fn () => $datasets->generateEmployees(100),
    ],
];

$labelWidth = max(array_map('strlen', $labels));
$total = count($benchmarks);

foreach ($benchmarks as $index => $benchmark) {
    $num = $index + 1;
    echo "[{$num}/{$total}] {$benchmark['name']}\n";

    $data = $benchmark['data']();
    $formatted = [
        'toon' => Formatters::toToon($data),
        'json' => Formatters::toJsonCompact($data),
        'xml' => Formatters::toXml($data, 'root'),
    ];

    // Count every format with every counter.
    $tokens = [];
    foreach ($counters as $counter) {
        $tokens[$counter->label] = [
            'toon' => $counter->count($formatted['toon']),
            'json' => $counter->count($formatted['json']),
            'xml' => $counter->count($formatted['xml']),
        ];
    }

    // Aligned per-counter table.
    printf("  %-{$labelWidth}s  %9s  %9s  %9s  %10s\n", 'Tokenizer', 'TOON', 'JSON', 'XML', 'TOON↓JSON');
    foreach ($labels as $label) {
        $t = $tokens[$label];
        $reduction = $t['json'] > 0 ? (($t['json'] - $t['toon']) / $t['json']) * 100 : 0;
        printf(
            "  %-{$labelWidth}s  %9s  %9s  %9s  %9s%%\n",
            $label,
            number_format($t['toon']),
            number_format($t['json']),
            number_format($t['xml']),
            number_format($reduction, 1)
        );
    }
    echo "\n";

    $report->addResult($benchmark['name'], $benchmark['description'], $tokens);
}

$reportPath = dirname(__DIR__).'/results/token-efficiency.md';
if (! is_dir(dirname($reportPath))) {
    mkdir(dirname($reportPath), 0755, true);
}
$report->save($reportPath);

echo "Report saved to: {$reportPath}\n";
echo "Benchmark complete.\n";
