<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use HelgeSverre\Toon\Toon;
use OpenAI\Client;

/**
 * Basic OpenAI Integration with TOON
 *
 * This example shows how to use TOON to reduce token consumption
 * when sending structured data to OpenAI's API.
 */

// Initialize OpenAI client
$apiKey = getenv('OPENAI_API_KEY') ?: 'your-api-key-here';
$client = OpenAI::client($apiKey);

// Example: User profile data
$userData = [
    'id' => 12345,
    'name' => 'Alice Johnson',
    'email' => 'alice@example.com',
    'preferences' => [
        'language' => 'en',
        'timezone' => 'America/New_York',
        'notifications' => true,
    ],
    'subscription' => [
        'plan' => 'premium',
        'status' => 'active',
        'expires_at' => '2025-12-31',
    ],
];

// Encode with TOON (compact format)
$toonData = Toon::encode($userData);

echo "=== TOON Encoding Demo ===\n\n";
echo "Original Data (JSON):\n";
echo json_encode($userData, JSON_PRETTY_PRINT)."\n\n";
echo "TOON Encoded:\n";
echo $toonData."\n\n";

// Token comparison
$stats = toon_compare($userData);
echo "Token Comparison:\n";
echo "- JSON: {$stats['json']} characters\n";
echo "- TOON: {$stats['toon']} characters\n";
echo "- Savings: {$stats['savings']} characters ({$stats['savings_percent']})\n\n";

// Send to OpenAI with TOON-encoded context
$response = $client->chat()->create([
    'model' => 'gpt-4o-mini',
    'messages' => [
        [
            'role' => 'system',
            'content' => 'You are a helpful assistant. User data is provided in TOON format (a compact, readable format).',
        ],
        [
            'role' => 'user',
            'content' => "Here is the user data:\n\n{$toonData}\n\nGenerate a personalized welcome message for this user.",
        ],
    ],
    'max_tokens' => 150,
]);

echo "=== OpenAI Response ===\n\n";
echo $response->choices[0]->message->content."\n";
