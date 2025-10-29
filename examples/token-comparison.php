<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\Toon;

/**
 * TOON vs JSON Token Comparison
 *
 * This example demonstrates token savings across different data types.
 */

// Test datasets
$datasets = [
    'User Profile' => [
        'id' => 789,
        'name' => 'Bob Smith',
        'email' => 'bob@company.com',
        'role' => 'admin',
        'active' => true,
        'created_at' => '2024-01-15T10:30:00Z',
    ],

    'Product Catalog' => [
        'products' => [
            ['id' => 1, 'name' => 'Widget A', 'price' => 29.99, 'stock' => 100],
            ['id' => 2, 'name' => 'Widget B', 'price' => 39.99, 'stock' => 50],
            ['id' => 3, 'name' => 'Widget C', 'price' => 19.99, 'stock' => 200],
            ['id' => 4, 'name' => 'Widget D', 'price' => 49.99, 'stock' => 75],
        ],
    ],

    'Analytics Data' => [
        'metrics' => [
            ['date' => '2025-01-01', 'views' => 1250, 'clicks' => 89, 'conversions' => 12],
            ['date' => '2025-01-02', 'views' => 1387, 'clicks' => 102, 'conversions' => 15],
            ['date' => '2025-01-03', 'views' => 1156, 'clicks' => 78, 'conversions' => 9],
            ['date' => '2025-01-04', 'views' => 1489, 'clicks' => 115, 'conversions' => 18],
            ['date' => '2025-01-05', 'views' => 1623, 'clicks' => 134, 'conversions' => 21],
        ],
    ],

    'Nested Structure' => [
        'company' => [
            'name' => 'Acme Corp',
            'founded' => 2020,
            'departments' => [
                [
                    'name' => 'Engineering',
                    'employees' => [
                        ['name' => 'Alice', 'role' => 'Senior Dev'],
                        ['name' => 'Bob', 'role' => 'Junior Dev'],
                    ],
                ],
                [
                    'name' => 'Marketing',
                    'employees' => [
                        ['name' => 'Carol', 'role' => 'Manager'],
                        ['name' => 'Dave', 'role' => 'Specialist'],
                    ],
                ],
            ],
        ],
    ],
];

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║          TOON vs JSON Token Comparison                        ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$totalJsonSize = 0;
$totalToonSize = 0;

foreach ($datasets as $name => $data) {
    echo "Dataset: {$name}\n";
    echo str_repeat('-', 70)."\n";

    // Compare different TOON configurations
    $configs = [
        'Default' => null,
        'Compact' => EncodeOptions::compact(),
        'Readable' => EncodeOptions::readable(),
        'Tabular' => EncodeOptions::tabular(),
    ];

    $jsonOutput = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $jsonSize = strlen($jsonOutput);

    echo sprintf("JSON:           %5d characters\n", $jsonSize);

    foreach ($configs as $configName => $options) {
        $toonOutput = Toon::encode($data, $options);
        $toonSize = strlen($toonOutput);
        $savings = (($jsonSize - $toonSize) / $jsonSize) * 100;

        echo sprintf("TOON %-10s %5d characters (-%.1f%%)\n", "({$configName}):", $toonSize, $savings);
    }

    echo "\n";

    // Add to totals (using default config)
    $totalJsonSize += $jsonSize;
    $totalToonSize += strlen(Toon::encode($data));
}

// Summary
echo str_repeat('=', 70)."\n";
echo "SUMMARY\n";
echo str_repeat('=', 70)."\n";
echo sprintf("Total JSON:  %5d characters\n", $totalJsonSize);
echo sprintf("Total TOON:  %5d characters\n", $totalToonSize);
echo sprintf("Savings:     %5d characters (%.1f%%)\n",
    $totalJsonSize - $totalToonSize,
    (($totalJsonSize - $totalToonSize) / $totalJsonSize) * 100
);

// Estimate token cost savings
$estimatedJsonTokens = ceil($totalJsonSize / 4);
$estimatedToonTokens = ceil($totalToonSize / 4);
$tokenSavings = $estimatedJsonTokens - $estimatedToonTokens;

echo "\n";
echo "Estimated Token Count (4 chars/token average):\n";
echo sprintf("JSON:   %d tokens\n", $estimatedJsonTokens);
echo sprintf("TOON:   %d tokens\n", $estimatedToonTokens);
echo sprintf("Saved:  %d tokens\n", $tokenSavings);

// Cost estimation (GPT-4 pricing as example: $0.03 per 1K tokens)
$costPerToken = 0.03 / 1000;
$jsonCost = $estimatedJsonTokens * $costPerToken;
$toonCost = $estimatedToonTokens * $costPerToken;
$costSavings = $jsonCost - $toonCost;

echo "\n";
echo "Estimated Cost (GPT-4 pricing: $0.03/1K tokens):\n";
echo sprintf("JSON:   $%.6f\n", $jsonCost);
echo sprintf("TOON:   $%.6f\n", $toonCost);
echo sprintf("Saved:  $%.6f per request\n", $costSavings);
echo sprintf("        $%.2f per 1,000 requests\n", $costSavings * 1000);
echo sprintf("        $%.2f per 100,000 requests\n", $costSavings * 100000);
