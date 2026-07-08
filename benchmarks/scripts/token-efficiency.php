#!/usr/bin/env php
<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../../vendor/autoload.php';

use Benchmarks\Datasets;
use Benchmarks\Formatters;
use Benchmarks\Report;
use Benchmarks\TokenCounter;

// Load environment variables from .env if it exists
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

// Count with one previous-generation model (Haiku 4.5, older tokenizer) and one
// current-generation model (Sonnet 5, newer tokenizer) so the tokenizer-generation
// difference in token counts is visible.
$models = ['claude-haiku-4-5', 'claude-sonnet-5'];
$apiKey = getenv('ANTHROPIC_API_KEY') ?: null;

// Initialize components
$datasets = new Datasets;

/** @var array<string, TokenCounter> $counters */
$counters = [];
foreach ($models as $model) {
    $counters[$model] = new TokenCounter($apiKey, $model);
}
$primary = $counters[$models[0]];
$report = new Report($primary->getMethod(), $models);

echo 'Models: '.implode(', ', $models)."\n";
echo "Token Counting Method: {$primary->getMethod()}\n";
if (! $primary->isUsingApi()) {
    echo "⚠️  Using estimation method (model-agnostic, so per-model counts will be identical).\n";
    echo "   Set ANTHROPIC_API_KEY in .env to see real per-model tokenizer differences.\n";
}
echo "\n";

// Define benchmarks
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

// Run benchmarks
$totalBenchmarks = count($benchmarks);
foreach ($benchmarks as $index => $benchmark) {
    $num = $index + 1;
    echo "[{$num}/{$totalBenchmarks}] Running: {$benchmark['name']}...\n";

    // Generate data
    $data = $benchmark['data']();

    // Format in all formats
    echo '  → Formatting data...';
    $toon = Formatters::toToon($data);
    $json = Formatters::toJsonCompact($data);
    $xml = Formatters::toXml($data, 'root');
    echo " ✓\n";

    // Count tokens with every model
    echo '  → Counting tokens...';
    $tokensByModel = [];
    foreach ($models as $model) {
        $tokensByModel[$model] = [
            'toon' => $counters[$model]->count($toon),
            'json' => $counters[$model]->count($json),
            'xml' => $counters[$model]->count($xml),
        ];
    }
    echo " ✓\n";

    // Display per-model results
    echo "  → Results:\n";
    foreach ($models as $model) {
        $t = $tokensByModel[$model];
        $jsonSavings = $t['json'] > 0 ? (($t['json'] - $t['toon']) / $t['json']) * 100 : 0;
        echo sprintf(
            "      %-16s TOON %s | JSON %s | XML %s  (TOON saves %s%% vs JSON)\n",
            $model,
            number_format($t['toon']),
            number_format($t['json']),
            number_format($t['xml']),
            number_format($jsonSavings, 1)
        );
    }

    // Add to report
    $report->addResult(
        $benchmark['name'],
        $benchmark['description'],
        $tokensByModel
    );

    echo "\n";
}

// Generate and save report
echo "Generating markdown report...\n";
$reportPath = __DIR__.'/../results/token-efficiency.md';

// Ensure results directory exists
if (! is_dir(dirname($reportPath))) {
    mkdir(dirname($reportPath), 0755, true);
}

$report->save($reportPath);

echo "✓ Report saved to: {$reportPath}\n\n";

echo "Benchmark complete.\n";
