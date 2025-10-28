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

// Initialize components
$datasets = new Datasets;
$tokenCounter = new TokenCounter;
$report = new Report($tokenCounter->getMethod());

echo "Token Counting Method: {$tokenCounter->getMethod()}\n";
if (! $tokenCounter->isUsingApi()) {
    echo "⚠️  Using estimation method. For accurate results, set ANTHROPIC_API_KEY in .env\n";
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

    // Count tokens
    echo '  → Counting tokens...';
    $tokens = [
        'toon' => $tokenCounter->count($toon),
        'json' => $tokenCounter->count($json),
        'xml' => $tokenCounter->count($xml),
    ];
    echo " ✓\n";

    // Display results
    echo "  → Results:\n";
    echo '      TOON: '.number_format($tokens['toon'])." tokens\n";
    echo '      JSON: '.number_format($tokens['json'])." tokens\n";
    echo '      XML:  '.number_format($tokens['xml'])." tokens\n";

    // Calculate savings
    $jsonSavings = (($tokens['json'] - $tokens['toon']) / $tokens['json']) * 100;
    $xmlSavings = (($tokens['xml'] - $tokens['toon']) / $tokens['xml']) * 100;

    echo '  → TOON saves '.number_format($jsonSavings, 1).'% vs JSON, ';
    echo number_format($xmlSavings, 1)."% vs XML\n";

    // Add to report
    $report->addResult(
        $benchmark['name'],
        $benchmark['description'],
        $tokens
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
