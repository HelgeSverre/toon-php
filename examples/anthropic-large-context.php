<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use Anthropic\Anthropic;
use Anthropic\Resources\Messages\MessageParam;
use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\Toon;

/**
 * Anthropic/Claude Large Context Optimization with TOON
 *
 * This example demonstrates how TOON helps fit more data into
 * Claude's large context window (200K tokens).
 */

// Initialize Anthropic client
$apiKey = getenv('ANTHROPIC_API_KEY') ?: 'your-api-key-here';
$client = Anthropic::factory()
    ->withApiKey($apiKey)
    ->make();

// Example: Large dataset of customer support tickets
$supportTickets = [];
for ($i = 1; $i <= 50; $i++) {
    $supportTickets[] = [
        'id' => $i,
        'customer' => "Customer {$i}",
        'subject' => "Issue #{$i}",
        'priority' => $i % 3 === 0 ? 'high' : ($i % 2 === 0 ? 'medium' : 'low'),
        'status' => $i > 40 ? 'open' : 'closed',
        'created_at' => date('Y-m-d H:i:s', strtotime("-{$i} days")),
    ];
}

// Compare encoding sizes
$jsonEncoded = json_encode(['tickets' => $supportTickets], JSON_PRETTY_PRINT);
$toonEncoded = Toon::encode(['tickets' => $supportTickets], EncodeOptions::compact());

echo "=== Large Context Optimization for Claude ===\n\n";
echo "Dataset: 50 customer support tickets\n\n";

echo "JSON Encoding:\n";
echo 'Size: '.strlen($jsonEncoded)." characters\n";
echo 'Estimated tokens: '.ceil(strlen($jsonEncoded) / 4)."\n\n";

echo "TOON Encoding:\n";
echo 'Size: '.strlen($toonEncoded)." characters\n";
echo 'Estimated tokens: '.ceil(strlen($toonEncoded) / 4)."\n\n";

$savings = strlen($jsonEncoded) - strlen($toonEncoded);
$savingsPercent = ($savings / strlen($jsonEncoded)) * 100;
echo "Savings: {$savings} characters (".number_format($savingsPercent, 1)."%)\n\n";

echo "TOON Preview (first 500 chars):\n";
echo str_repeat('-', 70)."\n";
echo substr($toonEncoded, 0, 500)."...\n";
echo str_repeat('-', 70)."\n\n";

// Send to Claude with TOON-encoded context
try {
    $response = $client->messages()->create([
        'model' => 'claude-sonnet-4-20250514',
        'max_tokens' => 300,
        'messages' => [
            MessageParam::with(
                role: 'user',
                content: <<<EOT
Here is a dataset of customer support tickets in TOON format (a compact, readable format).
Please analyze the tickets and provide a summary of:
1. High priority open tickets
2. Most common issues
3. Recommended actions

Data:
{$toonEncoded}
EOT
            ),
        ],
    ]);

    echo "=== Claude's Analysis ===\n\n";
    echo $response->content[0]->text."\n";
} catch (Exception $e) {
    echo "Note: Set ANTHROPIC_API_KEY environment variable to run this example\n";
    echo 'Error: '.$e->getMessage()."\n";
}

// Context window utilization comparison
echo "\n=== Context Window Utilization ===\n\n";
echo "If you were sending this data 100 times in a conversation:\n";
echo 'JSON:  '.number_format(ceil(strlen($jsonEncoded) / 4) * 100)." tokens\n";
echo 'TOON:  '.number_format(ceil(strlen($toonEncoded) / 4) * 100)." tokens\n";
echo 'Saved: '.number_format((ceil(strlen($jsonEncoded) / 4) - ceil(strlen($toonEncoded) / 4)) * 100)." tokens\n\n";
echo 'With TOON, you can fit '.number_format($savingsPercent, 1)."% more data in the same context window!\n";
