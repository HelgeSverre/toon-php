# Integrating TOON with OpenAI PHP Client

**Difficulty**: Intermediate
**Time to Complete**: 15-20 minutes
**PHP Version**: 8.1+

## What You'll Build

A production-ready OpenAI integration that:
- Formats chat messages and system prompts with TOON
- Optimizes function calling to reduce token usage
- Handles streaming responses efficiently
- Measures and tracks actual token savings
- Implements retry logic and error handling

## What You'll Learn

- Setting up OpenAI PHP client with TOON
- Formatting complex messages and conversations
- Optimizing function calling with TOON
- Measuring real token savings and costs
- Handling streaming responses
- Production best practices

## Prerequisites

- Completed Tutorial 1 (Getting Started with TOON)
- PHP 8.1+ with Composer
- OpenAI API key (free tier works)
- Basic understanding of OpenAI's chat API
- Familiarity with PHP async concepts (for streaming)

## Introduction

OpenAI's GPT models charge by token consumption. Every character in your prompts costs money. By using TOON instead of JSON for structured data, you can reduce costs by 30-60% while maintaining the same functionality.

This tutorial shows how to integrate TOON seamlessly with the OpenAI PHP client, particularly for complex use cases like function calling and streaming responses.

## Step 1: Installation and Setup

First, install the required packages:

```bash
mkdir openai-toon-integration
cd openai-toon-integration

composer require helgesverre/toon openai-php/client guzzlehttp/guzzle symfony/http-client nyholm/psr7
```

Create a `.env` file for your API key:

```bash
# .env
OPENAI_API_KEY=sk-your-api-key-here
```

Create a bootstrap file `bootstrap.php`:

```php
<?php
require_once 'vendor/autoload.php';

use HelgeSverre\Toon\Toon;
use OpenAI\Client;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Validate API key
$apiKey = $_ENV['OPENAI_API_KEY'] ?? null;
if (!$apiKey || str_starts_with($apiKey, 'sk-your')) {
    die("Please set a valid OPENAI_API_KEY in your .env file\n");
}

// Create OpenAI client
function createOpenAIClient(): Client {
    return OpenAI::client($_ENV['OPENAI_API_KEY']);
}

// Token cost calculator
class TokenCostCalculator {
    private const PRICES = [
        'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],
        'gpt-4' => ['input' => 0.03, 'output' => 0.06],
        'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03],
    ];

    public static function calculate(string $model, int $inputTokens, int $outputTokens): array {
        $prices = self::PRICES[$model] ?? self::PRICES['gpt-3.5-turbo'];

        return [
            'input_cost' => ($inputTokens / 1000) * $prices['input'],
            'output_cost' => ($outputTokens / 1000) * $prices['output'],
            'total_cost' => (($inputTokens / 1000) * $prices['input']) +
                           (($outputTokens / 1000) * $prices['output'])
        ];
    }
}

echo "OpenAI client configured successfully!\n";
```

## Step 2: Basic Message Formatting

Create `basic-messages.php` to demonstrate message formatting:

```php
<?php
require_once 'bootstrap.php';

use HelgeSverre\Toon\Toon;

class MessageFormatter {
    public static function formatUserData(array $userData): string {
        return Toon::encode($userData);
    }

    public static function createSystemPrompt(array $context): string {
        $toonContext = Toon::encode($context);

        return <<<PROMPT
You are an AI assistant analyzing user data. The data is provided in TOON format.

TOON Format Guide:
- Objects use key:value pairs with indentation for nesting
- Arrays show [length]: item1,item2,item3
- Tabular data uses [rows]{fields}: row1_values

Current Context:
$toonContext

Respond concisely and reference the data accurately.
PROMPT;
    }

    public static function formatConversation(array $messages): array {
        $formatted = [];

        foreach ($messages as $message) {
            if (isset($message['data'])) {
                // Convert data to TOON format
                $content = $message['content'] ?? '';
                $toonData = Toon::encode($message['data']);

                $formatted[] = [
                    'role' => $message['role'],
                    'content' => $content . "\n\nData:\n" . $toonData
                ];
            } else {
                $formatted[] = $message;
            }
        }

        return $formatted;
    }
}

// Example: Customer support analysis
$customerData = [
    'customer_id' => 'CUST-2025-8934',
    'name' => 'Jennifer Walsh',
    'account' => [
        'type' => 'premium',
        'since' => '2023-06-15',
        'monthly_value' => 149.99,
        'status' => 'active'
    ],
    'recent_tickets' => [
        ['id' => 'TKT-001', 'date' => '2025-01-10', 'issue' => 'Login problems', 'resolved' => true],
        ['id' => 'TKT-002', 'date' => '2025-01-15', 'issue' => 'Billing question', 'resolved' => true],
        ['id' => 'TKT-003', 'date' => '2025-01-20', 'issue' => 'Feature request', 'resolved' => false]
    ],
    'satisfaction_scores' => [9, 8, 10, 9, 7],
    'products' => ['CRM Pro', 'Analytics Plus', 'Support Suite']
];

// Compare JSON vs TOON
echo "=== Data Format Comparison ===\n\n";

$jsonFormat = json_encode($customerData, JSON_PRETTY_PRINT);
$toonFormat = MessageFormatter::formatUserData($customerData);

echo "JSON Format (" . strlen($jsonFormat) . " chars):\n";
echo substr($jsonFormat, 0, 400) . "...\n\n";

echo "TOON Format (" . strlen($toonFormat) . " chars):\n";
echo $toonFormat . "\n\n";

$reduction = round((1 - strlen($toonFormat) / strlen($jsonFormat)) * 100, 1);
echo "Character reduction: {$reduction}%\n\n";

// Create conversation with TOON data
$messages = MessageFormatter::formatConversation([
    [
        'role' => 'system',
        'content' => MessageFormatter::createSystemPrompt([
            'task' => 'customer_analysis',
            'focus' => ['satisfaction', 'retention_risk', 'upsell_opportunities']
        ])
    ],
    [
        'role' => 'user',
        'content' => 'Analyze this customer and provide insights:',
        'data' => $customerData
    ]
]);

echo "=== Formatted Messages for OpenAI ===\n\n";
foreach ($messages as $i => $message) {
    echo "Message $i ({$message['role']}):\n";
    echo substr($message['content'], 0, 300) . "...\n\n";
}

// Make actual API call (uncomment to test)
/*
$client = createOpenAIClient();
$response = $client->chat()->create([
    'model' => 'gpt-3.5-turbo',
    'messages' => $messages,
    'temperature' => 0.7,
    'max_tokens' => 500
]);

echo "=== OpenAI Response ===\n";
echo $response->choices[0]->message->content . "\n\n";

echo "=== Token Usage ===\n";
echo "Prompt tokens: {$response->usage->promptTokens}\n";
echo "Completion tokens: {$response->usage->completionTokens}\n";
echo "Total tokens: {$response->usage->totalTokens}\n";

$cost = TokenCostCalculator::calculate(
    'gpt-3.5-turbo',
    $response->usage->promptTokens,
    $response->usage->completionTokens
);

echo "\n=== Cost Analysis ===\n";
echo "Input cost: $" . number_format($cost['input_cost'], 4) . "\n";
echo "Output cost: $" . number_format($cost['output_cost'], 4) . "\n";
echo "Total cost: $" . number_format($cost['total_cost'], 4) . "\n";
*/
```

## Step 3: Optimizing Function Calling

Create `function-calling.php` to demonstrate TOON with function calling:

```php
<?php
require_once 'bootstrap.php';

use HelgeSverre\Toon\Toon;
use HelgeSverre\Toon\EncodeOptions;

class FunctionCallOptimizer {
    private static $options;

    public static function init() {
        self::$options = new EncodeOptions(indent: 2, delimiter: ',');
    }

    public static function formatFunctionResult(array $result): string {
        return Toon::encode($result, self::$options);
    }

    public static function createTools(): array {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_products',
                    'description' => 'Search for products in the catalog',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Search query'
                            ],
                            'category' => [
                                'type' => 'string',
                                'enum' => ['electronics', 'clothing', 'books', 'home'],
                                'description' => 'Product category'
                            ],
                            'max_results' => [
                                'type' => 'integer',
                                'description' => 'Maximum number of results',
                                'default' => 5
                            ]
                        ],
                        'required' => ['query']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_order_details',
                    'description' => 'Get details for a specific order',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'order_id' => [
                                'type' => 'string',
                                'description' => 'Order ID'
                            ]
                        ],
                        'required' => ['order_id']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'calculate_shipping',
                    'description' => 'Calculate shipping cost and delivery time',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'items' => [
                                'type' => 'array',
                                'description' => 'Array of item IDs'
                            ],
                            'zip_code' => [
                                'type' => 'string',
                                'description' => 'Delivery ZIP code'
                            ],
                            'express' => [
                                'type' => 'boolean',
                                'description' => 'Use express shipping',
                                'default' => false
                            ]
                        ],
                        'required' => ['items', 'zip_code']
                    ]
                ]
            ]
        ];
    }

    public static function executeFunction(string $name, array $arguments): array {
        switch ($name) {
            case 'search_products':
                return self::searchProducts(
                    $arguments['query'],
                    $arguments['category'] ?? null,
                    $arguments['max_results'] ?? 5
                );

            case 'get_order_details':
                return self::getOrderDetails($arguments['order_id']);

            case 'calculate_shipping':
                return self::calculateShipping(
                    $arguments['items'],
                    $arguments['zip_code'],
                    $arguments['express'] ?? false
                );

            default:
                return ['error' => 'Unknown function: ' . $name];
        }
    }

    private static function searchProducts(string $query, ?string $category, int $maxResults): array {
        // Simulate product search
        $allProducts = [
            ['id' => 'LAPTOP-001', 'name' => 'ThinkPad X1 Carbon', 'category' => 'electronics', 'price' => 1899.99, 'stock' => 12],
            ['id' => 'LAPTOP-002', 'name' => 'MacBook Air M2', 'category' => 'electronics', 'price' => 1199.99, 'stock' => 8],
            ['id' => 'PHONE-001', 'name' => 'iPhone 15 Pro', 'category' => 'electronics', 'price' => 1099.99, 'stock' => 25],
            ['id' => 'BOOK-001', 'name' => 'Clean Code', 'category' => 'books', 'price' => 39.99, 'stock' => 50],
            ['id' => 'BOOK-002', 'name' => 'Design Patterns', 'category' => 'books', 'price' => 49.99, 'stock' => 30],
        ];

        $results = array_filter($allProducts, function($product) use ($query, $category) {
            $matchesQuery = stripos($product['name'], $query) !== false;
            $matchesCategory = !$category || $product['category'] === $category;
            return $matchesQuery && $matchesCategory;
        });

        return [
            'query' => $query,
            'category' => $category,
            'results' => array_slice(array_values($results), 0, $maxResults),
            'total_found' => count($results)
        ];
    }

    private static function getOrderDetails(string $orderId): array {
        // Simulate order lookup
        return [
            'order_id' => $orderId,
            'status' => 'processing',
            'created_at' => '2025-01-20T10:30:00Z',
            'customer' => [
                'name' => 'John Doe',
                'email' => 'john.doe@example.com'
            ],
            'items' => [
                ['product_id' => 'LAPTOP-001', 'name' => 'ThinkPad X1 Carbon', 'quantity' => 1, 'price' => 1899.99],
                ['product_id' => 'PHONE-001', 'name' => 'iPhone 15 Pro', 'quantity' => 2, 'price' => 1099.99]
            ],
            'total' => 4099.97,
            'shipping' => [
                'method' => 'standard',
                'cost' => 15.99,
                'estimated_delivery' => '2025-01-25'
            ]
        ];
    }

    private static function calculateShipping(array $items, string $zipCode, bool $express): array {
        $baseRate = $express ? 25.99 : 9.99;
        $perItemRate = $express ? 3.00 : 1.50;

        $cost = $baseRate + (count($items) * $perItemRate);
        $days = $express ? rand(1, 2) : rand(3, 7);

        return [
            'items_count' => count($items),
            'zip_code' => $zipCode,
            'shipping_method' => $express ? 'express' : 'standard',
            'cost' => round($cost, 2),
            'estimated_days' => $days,
            'estimated_delivery' => date('Y-m-d', strtotime("+$days days"))
        ];
    }
}

// Initialize optimizer
FunctionCallOptimizer::init();

// Example conversation with function calling
$conversation = [
    'messages' => [
        ['role' => 'system', 'content' => 'You are a helpful e-commerce assistant. When providing data from functions, the results will be in TOON format for efficiency.'],
        ['role' => 'user', 'content' => 'I want to buy a laptop. Can you search for ThinkPad models?']
    ]
];

echo "=== Function Calling with TOON ===\n\n";

// Simulate function call
$functionCall = [
    'name' => 'search_products',
    'arguments' => ['query' => 'ThinkPad', 'category' => 'electronics', 'max_results' => 3]
];

echo "Function call: {$functionCall['name']}\n";
echo "Arguments: " . json_encode($functionCall['arguments']) . "\n\n";

// Execute function
$result = FunctionCallOptimizer::executeFunction($functionCall['name'], $functionCall['arguments']);

// Compare JSON vs TOON response
$jsonResult = json_encode($result, JSON_PRETTY_PRINT);
$toonResult = FunctionCallOptimizer::formatFunctionResult($result);

echo "=== Function Result Comparison ===\n\n";
echo "JSON (" . strlen($jsonResult) . " chars):\n";
echo $jsonResult . "\n\n";

echo "TOON (" . strlen($toonResult) . " chars):\n";
echo $toonResult . "\n\n";

$savings = round((1 - strlen($toonResult) / strlen($jsonResult)) * 100, 1);
echo "Size reduction: {$savings}%\n\n";

// Full example with OpenAI (uncomment to test)
/*
$client = createOpenAIClient();

// Initial request with tools
$response = $client->chat()->create([
    'model' => 'gpt-3.5-turbo',
    'messages' => $conversation['messages'],
    'tools' => FunctionCallOptimizer::createTools(),
    'tool_choice' => 'auto'
]);

// Check if function was called
if ($response->choices[0]->message->toolCalls) {
    foreach ($response->choices[0]->message->toolCalls as $toolCall) {
        $functionName = $toolCall->function->name;
        $arguments = json_decode($toolCall->function->arguments, true);

        echo "AI called function: $functionName\n";
        echo "With arguments: " . json_encode($arguments) . "\n\n";

        // Execute function
        $result = FunctionCallOptimizer::executeFunction($functionName, $arguments);

        // Format result with TOON
        $toonResult = FunctionCallOptimizer::formatFunctionResult($result);

        // Add to conversation
        $conversation['messages'][] = $response->choices[0]->message->toArray();
        $conversation['messages'][] = [
            'role' => 'tool',
            'tool_call_id' => $toolCall->id,
            'content' => $toonResult
        ];

        // Get final response
        $finalResponse = $client->chat()->create([
            'model' => 'gpt-3.5-turbo',
            'messages' => $conversation['messages']
        ]);

        echo "=== Final AI Response ===\n";
        echo $finalResponse->choices[0]->message->content . "\n\n";

        echo "=== Token Usage ===\n";
        echo "First call: {$response->usage->totalTokens} tokens\n";
        echo "Second call: {$finalResponse->usage->totalTokens} tokens\n";
        echo "Total: " . ($response->usage->totalTokens + $finalResponse->usage->totalTokens) . " tokens\n";
    }
}
*/

// Demonstrate multiple function calls
echo "=== Multiple Function Calls Example ===\n\n";

$complexQuery = "I need to check order ORD-2025-1234 and calculate shipping for 3 items to ZIP 94105 with express delivery";

$functionSequence = [
    ['name' => 'get_order_details', 'arguments' => ['order_id' => 'ORD-2025-1234']],
    ['name' => 'calculate_shipping', 'arguments' => ['items' => ['ITEM-1', 'ITEM-2', 'ITEM-3'], 'zip_code' => '94105', 'express' => true]]
];

$totalJsonSize = 0;
$totalToonSize = 0;

foreach ($functionSequence as $func) {
    $result = FunctionCallOptimizer::executeFunction($func['name'], $func['arguments']);

    $jsonSize = strlen(json_encode($result));
    $toonSize = strlen(FunctionCallOptimizer::formatFunctionResult($result));

    $totalJsonSize += $jsonSize;
    $totalToonSize += $toonSize;

    echo "Function: {$func['name']}\n";
    echo "  JSON size: $jsonSize chars\n";
    echo "  TOON size: $toonSize chars\n";
    echo "  Savings: " . round((1 - $toonSize / $jsonSize) * 100, 1) . "%\n\n";
}

echo "Total sizes:\n";
echo "  JSON: $totalJsonSize chars\n";
echo "  TOON: $totalToonSize chars\n";
echo "  Overall savings: " . round((1 - $totalToonSize / $totalJsonSize) * 100, 1) . "%\n";
```

## Step 4: Streaming Responses

Create `streaming-responses.php` for handling streaming with TOON:

```php
<?php
require_once 'bootstrap.php';

use HelgeSverre\Toon\Toon;

class StreamingHandler {
    private $buffer = '';
    private $tokenCount = 0;
    private $startTime;

    public function __construct() {
        $this->startTime = microtime(true);
    }

    public function processChunk(string $chunk): void {
        $this->buffer .= $chunk;
        $this->tokenCount++;

        // Process complete sentences
        if (preg_match('/[.!?]\s/', $chunk)) {
            echo $chunk;
            flush();
        }
    }

    public function getStats(): array {
        $duration = microtime(true) - $this->startTime;

        return [
            'total_tokens' => $this->tokenCount,
            'duration_seconds' => round($duration, 2),
            'tokens_per_second' => round($this->tokenCount / $duration, 1),
            'buffer_size' => strlen($this->buffer)
        ];
    }
}

// Prepare large dataset for streaming
$analyticsData = [
    'period' => '2025-01',
    'metrics' => [
        'visitors' => 125847,
        'page_views' => 458921,
        'bounce_rate' => 42.3,
        'avg_session_duration' => 186
    ],
    'top_pages' => [
        ['url' => '/home', 'views' => 98234, 'avg_time' => 45],
        ['url' => '/products', 'views' => 67123, 'avg_time' => 120],
        ['url' => '/about', 'views' => 34521, 'avg_time' => 38],
        ['url' => '/contact', 'views' => 12890, 'avg_time' => 95],
        ['url' => '/blog', 'views' => 45678, 'avg_time' => 234]
    ],
    'traffic_sources' => [
        ['source' => 'organic', 'sessions' => 45234, 'conversion_rate' => 3.4],
        ['source' => 'direct', 'sessions' => 34123, 'conversion_rate' => 2.8],
        ['source' => 'social', 'sessions' => 23456, 'conversion_rate' => 1.9],
        ['source' => 'paid', 'sessions' => 12345, 'conversion_rate' => 4.2],
        ['source' => 'email', 'sessions' => 10687, 'conversion_rate' => 5.1]
    ],
    'devices' => [
        'mobile' => 68.4,
        'desktop' => 28.3,
        'tablet' => 3.3
    ],
    'conversions' => [
        'total' => 3847,
        'value' => 284739.50,
        'avg_order_value' => 74.01
    ]
];

echo "=== Streaming Response with TOON Data ===\n\n";

$toonData = Toon::encode($analyticsData);
echo "Analytics data in TOON format (" . strlen($toonData) . " chars):\n";
echo $toonData . "\n\n";

// Prepare streaming request
$streamingPrompt = "Analyze this website analytics data and provide detailed insights about user behavior, conversion optimization opportunities, and recommendations. Format your response with clear sections and bullet points.\n\nData:\n" . $toonData;

echo "=== Simulated Streaming Response ===\n\n";

// Simulate streaming (uncomment for real API)
/*
$client = createOpenAIClient();
$handler = new StreamingHandler();

$stream = $client->chat()->createStreamed([
    'model' => 'gpt-3.5-turbo',
    'messages' => [
        ['role' => 'system', 'content' => 'You are a web analytics expert. Analyze the provided data in TOON format and give actionable insights.'],
        ['role' => 'user', 'content' => $streamingPrompt]
    ],
    'temperature' => 0.7,
    'max_tokens' => 1000,
    'stream' => true
]);

echo "Streaming response:\n";
echo str_repeat('-', 60) . "\n";

foreach ($stream as $response) {
    if (isset($response->choices[0]->delta->content)) {
        $chunk = $response->choices[0]->delta->content;
        $handler->processChunk($chunk);
    }
}

echo "\n" . str_repeat('-', 60) . "\n";

$stats = $handler->getStats();
echo "\n=== Streaming Statistics ===\n";
echo "Total tokens: {$stats['total_tokens']}\n";
echo "Duration: {$stats['duration_seconds']} seconds\n";
echo "Speed: {$stats['tokens_per_second']} tokens/second\n";
*/

// Demonstrate chunk processing
echo "Example of chunk processing for UI updates:\n\n";

class UIStreamHandler {
    private $sections = [];
    private $currentSection = '';
    private $buffer = '';

    public function processForUI(string $chunk): ?array {
        $this->buffer .= $chunk;

        // Detect section headers
        if (preg_match('/^##\s+(.+)$/m', $this->buffer, $matches)) {
            if ($this->currentSection) {
                $this->sections[$this->currentSection] = trim($this->buffer);
            }
            $this->currentSection = $matches[1];
            $this->buffer = '';

            return [
                'type' => 'section',
                'name' => $this->currentSection,
                'content' => null
            ];
        }

        // Detect bullet points
        if (preg_match('/^\s*[-*]\s+(.+)$/m', $chunk, $matches)) {
            return [
                'type' => 'bullet',
                'section' => $this->currentSection,
                'content' => $matches[1]
            ];
        }

        return null;
    }

    public function getSections(): array {
        return $this->sections;
    }
}

$uiHandler = new UIStreamHandler();
$simulatedChunks = [
    "## Traffic Analysis\n",
    "- Mobile traffic dominates at 68.4%\n",
    "- Desktop users show higher engagement\n",
    "## Conversion Insights\n",
    "- Email has the highest conversion rate at 5.1%\n",
    "- Paid traffic converts at 4.2%\n"
];

foreach ($simulatedChunks as $chunk) {
    $update = $uiHandler->processForUI($chunk);
    if ($update) {
        echo "UI Update: " . json_encode($update) . "\n";
    }
}
```

## Step 5: Performance Benchmarking

Create `benchmark.php` to measure actual performance:

```php
<?php
require_once 'bootstrap.php';

use HelgeSverre\Toon\Toon;
use HelgeSverre\Toon\EncodeOptions;

class PerformanceBenchmark {
    private $results = [];

    public function runBenchmark(string $name, array $data, callable $test): void {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $result = $test($data);

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $this->results[$name] = [
            'duration_ms' => round(($endTime - $startTime) * 1000, 2),
            'memory_bytes' => $endMemory - $startMemory,
            'size_bytes' => strlen($result),
            'result' => $result
        ];
    }

    public function getComparison(): array {
        $json = $this->results['json'] ?? null;
        $toon = $this->results['toon'] ?? null;

        if (!$json || !$toon) {
            return ['error' => 'Run both JSON and TOON benchmarks first'];
        }

        return [
            'size_reduction' => round((1 - $toon['size_bytes'] / $json['size_bytes']) * 100, 1),
            'speed_difference' => round($toon['duration_ms'] - $json['duration_ms'], 2),
            'memory_difference' => $toon['memory_bytes'] - $json['memory_bytes'],
            'json_stats' => $json,
            'toon_stats' => $toon
        ];
    }
}

// Generate test datasets
function generateTestData(int $size): array {
    $data = [
        'metadata' => [
            'version' => '1.0.0',
            'timestamp' => date('Y-m-d H:i:s'),
            'environment' => 'production'
        ],
        'records' => []
    ];

    for ($i = 0; $i < $size; $i++) {
        $data['records'][] = [
            'id' => 'REC-' . str_pad((string)$i, 6, '0', STR_PAD_LEFT),
            'user_id' => 'USR-' . rand(1000, 9999),
            'action' => ['login', 'view', 'purchase', 'logout'][rand(0, 3)],
            'timestamp' => date('Y-m-d H:i:s', time() - rand(0, 86400)),
            'ip_address' => rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255),
            'user_agent' => 'Mozilla/5.0 (compatible; Bot ' . rand(1, 100) . ')',
            'response_time_ms' => rand(10, 500),
            'status_code' => [200, 201, 204, 304, 404, 500][rand(0, 5)],
            'bytes_sent' => rand(100, 10000)
        ];
    }

    return $data;
}

echo "=== Performance Benchmark ===\n\n";

// Test different data sizes
$sizes = [10, 50, 100, 500, 1000];

foreach ($sizes as $size) {
    echo "Testing with $size records...\n";

    $testData = generateTestData($size);
    $benchmark = new PerformanceBenchmark();

    // Benchmark JSON
    $benchmark->runBenchmark('json', $testData, function($data) {
        return json_encode($data, JSON_PRETTY_PRINT);
    });

    // Benchmark TOON
    $benchmark->runBenchmark('toon', $testData, function($data) {
        return Toon::encode($data);
    });

    $comparison = $benchmark->getComparison();

    echo "  JSON: {$comparison['json_stats']['size_bytes']} bytes in {$comparison['json_stats']['duration_ms']}ms\n";
    echo "  TOON: {$comparison['toon_stats']['size_bytes']} bytes in {$comparison['toon_stats']['duration_ms']}ms\n";
    echo "  Size reduction: {$comparison['size_reduction']}%\n";
    echo "  Speed difference: {$comparison['speed_difference']}ms\n\n";
}

// Real-world scenario benchmark
echo "=== Real-World Scenario: Chat History ===\n\n";

$chatHistory = [
    'session_id' => 'CHAT-2025-98234',
    'started_at' => '2025-01-20T10:00:00Z',
    'messages' => []
];

// Generate realistic chat history
for ($i = 0; $i < 20; $i++) {
    $chatHistory['messages'][] = [
        'id' => $i + 1,
        'role' => $i % 2 == 0 ? 'user' : 'assistant',
        'content' => $i % 2 == 0
            ? "User question about topic " . rand(1, 10) . " with some details and context that makes the message longer."
            : "Assistant response providing helpful information about the topic, including multiple points and explanations that span several sentences. This represents a typical AI response with structured information and clear explanations.",
        'timestamp' => date('Y-m-d H:i:s', time() - (20 - $i) * 60),
        'tokens' => rand(50, 200)
    ];
}

$jsonChat = json_encode($chatHistory, JSON_PRETTY_PRINT);
$toonChat = Toon::encode($chatHistory);

echo "Chat history with 20 messages:\n";
echo "  JSON: " . strlen($jsonChat) . " characters\n";
echo "  TOON: " . strlen($toonChat) . " characters\n";
echo "  Reduction: " . round((1 - strlen($toonChat) / strlen($jsonChat)) * 100, 1) . "%\n\n";

// Calculate token savings for API calls
$estimatedJsonTokens = ceil(strlen($jsonChat) / 4);
$estimatedToonTokens = ceil(strlen($toonChat) / 4);
$tokensSaved = $estimatedJsonTokens - $estimatedToonTokens;

echo "Estimated token usage:\n";
echo "  JSON: ~$estimatedJsonTokens tokens\n";
echo "  TOON: ~$estimatedToonTokens tokens\n";
echo "  Saved: ~$tokensSaved tokens\n\n";

// Cost projection
$costPerThousandTokens = 0.002; // GPT-3.5-turbo pricing
$costSavedPerCall = ($tokensSaved / 1000) * $costPerThousandTokens;

echo "Cost projections (GPT-3.5-turbo):\n";
echo "  Per API call: $" . number_format($costSavedPerCall, 4) . " saved\n";
echo "  Per 1,000 calls: $" . number_format($costSavedPerCall * 1000, 2) . " saved\n";
echo "  Per 100,000 calls: $" . number_format($costSavedPerCall * 100000, 2) . " saved\n";
echo "  Per million calls: $" . number_format($costSavedPerCall * 1000000, 2) . " saved\n";
```

## Step 6: Production Best Practices

Create `production-example.php` with a complete production-ready implementation:

```php
<?php
require_once 'bootstrap.php';

use HelgeSverre\Toon\Toon;
use HelgeSverre\Toon\EncodeOptions;

class OpenAIToonClient {
    private $client;
    private $options;
    private $metricsCollector;
    private $cache;

    public function __construct(string $apiKey) {
        $this->client = OpenAI::client($apiKey);
        $this->options = new EncodeOptions(indent: 2);
        $this->metricsCollector = new MetricsCollector();
        $this->cache = new SimpleCache();
    }

    public function chat(array $messages, array $options = []): array {
        // Format messages with TOON
        $formattedMessages = $this->formatMessages($messages);

        // Check cache
        $cacheKey = $this->getCacheKey($formattedMessages, $options);
        if ($cached = $this->cache->get($cacheKey)) {
            $this->metricsCollector->recordCacheHit();
            return $cached;
        }

        // Prepare request with retry logic
        $attempts = 0;
        $maxAttempts = 3;
        $lastError = null;

        while ($attempts < $maxAttempts) {
            try {
                $startTime = microtime(true);

                $response = $this->client->chat()->create(array_merge([
                    'model' => $options['model'] ?? 'gpt-3.5-turbo',
                    'messages' => $formattedMessages,
                    'temperature' => $options['temperature'] ?? 0.7,
                    'max_tokens' => $options['max_tokens'] ?? 1000,
                ], $options));

                $duration = microtime(true) - $startTime;

                // Collect metrics
                $this->metricsCollector->recordRequest([
                    'duration' => $duration,
                    'tokens_prompt' => $response->usage->promptTokens,
                    'tokens_completion' => $response->usage->completionTokens,
                    'model' => $options['model'] ?? 'gpt-3.5-turbo'
                ]);

                $result = [
                    'content' => $response->choices[0]->message->content,
                    'usage' => $response->usage->toArray(),
                    'model' => $response->model,
                    'finish_reason' => $response->choices[0]->finishReason
                ];

                // Cache successful responses
                $this->cache->set($cacheKey, $result, 3600); // 1 hour TTL

                return $result;

            } catch (Exception $e) {
                $lastError = $e;
                $attempts++;

                if ($attempts < $maxAttempts) {
                    // Exponential backoff
                    $delay = pow(2, $attempts) * 1000000; // microseconds
                    usleep($delay);

                    $this->metricsCollector->recordRetry($attempts);
                }
            }
        }

        $this->metricsCollector->recordError($lastError);
        throw $lastError;
    }

    private function formatMessages(array $messages): array {
        $formatted = [];

        foreach ($messages as $message) {
            if (isset($message['data'])) {
                $toonData = Toon::encode($message['data'], $this->options);
                $formatted[] = [
                    'role' => $message['role'],
                    'content' => ($message['content'] ?? '') . "\n\n" . $toonData
                ];
            } else {
                $formatted[] = $message;
            }
        }

        return $formatted;
    }

    private function getCacheKey(array $messages, array $options): string {
        return md5(json_encode(['messages' => $messages, 'options' => $options]));
    }

    public function getMetrics(): array {
        return $this->metricsCollector->getMetrics();
    }
}

class MetricsCollector {
    private $metrics = [
        'total_requests' => 0,
        'total_tokens' => 0,
        'total_cost' => 0,
        'cache_hits' => 0,
        'retries' => 0,
        'errors' => 0,
        'average_duration' => 0
    ];

    private $requestDurations = [];

    public function recordRequest(array $data): void {
        $this->metrics['total_requests']++;
        $this->metrics['total_tokens'] += $data['tokens_prompt'] + $data['tokens_completion'];

        // Calculate cost based on model
        $cost = $this->calculateCost(
            $data['model'],
            $data['tokens_prompt'],
            $data['tokens_completion']
        );
        $this->metrics['total_cost'] += $cost;

        // Track duration
        $this->requestDurations[] = $data['duration'];
        $this->metrics['average_duration'] = array_sum($this->requestDurations) / count($this->requestDurations);
    }

    public function recordCacheHit(): void {
        $this->metrics['cache_hits']++;
    }

    public function recordRetry(int $attemptNumber): void {
        $this->metrics['retries']++;
    }

    public function recordError(Exception $error): void {
        $this->metrics['errors']++;
    }

    public function getMetrics(): array {
        return $this->metrics;
    }

    private function calculateCost(string $model, int $inputTokens, int $outputTokens): float {
        $prices = [
            'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],
            'gpt-4' => ['input' => 0.03, 'output' => 0.06]
        ];

        $modelPrices = $prices[$model] ?? $prices['gpt-3.5-turbo'];

        return ($inputTokens / 1000 * $modelPrices['input']) +
               ($outputTokens / 1000 * $modelPrices['output']);
    }
}

class SimpleCache {
    private $cache = [];

    public function get(string $key) {
        if (isset($this->cache[$key])) {
            $item = $this->cache[$key];
            if ($item['expires'] > time()) {
                return $item['value'];
            }
            unset($this->cache[$key]);
        }
        return null;
    }

    public function set(string $key, $value, int $ttl): void {
        $this->cache[$key] = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
    }
}

// Usage example
echo "=== Production OpenAI + TOON Client ===\n\n";

$client = new OpenAIToonClient($_ENV['OPENAI_API_KEY'] ?? 'demo-key');

// Example: Customer service automation
$customerContext = [
    'customer_id' => 'CUST-8934',
    'name' => 'Sarah Mitchell',
    'account' => [
        'type' => 'business',
        'mrr' => 499.99,
        'seats' => 25,
        'renewal_date' => '2025-06-15'
    ],
    'recent_tickets' => [
        ['id' => 'T-001', 'subject' => 'Login issues', 'status' => 'resolved', 'satisfaction' => 5],
        ['id' => 'T-002', 'subject' => 'Feature request', 'status' => 'pending', 'priority' => 'medium'],
        ['id' => 'T-003', 'subject' => 'Billing question', 'status' => 'open', 'priority' => 'high']
    ],
    'usage_stats' => [
        'daily_active_users' => 18,
        'api_calls_month' => 45234,
        'storage_gb' => 127.3
    ]
];

$messages = [
    [
        'role' => 'system',
        'content' => 'You are a customer success manager. Analyze customer data provided in TOON format and provide recommendations.'
    ],
    [
        'role' => 'user',
        'content' => 'Please analyze this customer account and suggest actions to improve satisfaction and prevent churn:',
        'data' => $customerContext
    ]
];

echo "Customer context in TOON format:\n";
echo Toon::encode($customerContext) . "\n\n";

// Simulate API call (uncomment for real usage)
/*
try {
    $response = $client->chat($messages, [
        'model' => 'gpt-3.5-turbo',
        'temperature' => 0.7,
        'max_tokens' => 500
    ]);

    echo "=== AI Response ===\n";
    echo $response['content'] . "\n\n";

    echo "=== Usage Stats ===\n";
    echo "Prompt tokens: {$response['usage']['prompt_tokens']}\n";
    echo "Completion tokens: {$response['usage']['completion_tokens']}\n";
    echo "Total tokens: {$response['usage']['total_tokens']}\n\n";

    // Get metrics
    $metrics = $client->getMetrics();
    echo "=== Session Metrics ===\n";
    echo "Total requests: {$metrics['total_requests']}\n";
    echo "Cache hits: {$metrics['cache_hits']}\n";
    echo "Total tokens: {$metrics['total_tokens']}\n";
    echo "Total cost: $" . number_format($metrics['total_cost'], 4) . "\n";
    echo "Average response time: " . round($metrics['average_duration'], 2) . "s\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
*/

echo "=== TOON vs JSON Token Comparison ===\n\n";

$jsonSize = strlen(json_encode($customerContext));
$toonSize = strlen(Toon::encode($customerContext));

echo "JSON size: $jsonSize characters (~" . ceil($jsonSize / 4) . " tokens)\n";
echo "TOON size: $toonSize characters (~" . ceil($toonSize / 4) . " tokens)\n";
echo "Reduction: " . round((1 - $toonSize / $jsonSize) * 100, 1) . "%\n\n";

echo "For 1000 similar requests:\n";
$savedTokens = (ceil($jsonSize / 4) - ceil($toonSize / 4)) * 1000;
$savedCost = ($savedTokens / 1000) * 0.0005; // GPT-3.5 input pricing
echo "  Tokens saved: $savedTokens\n";
echo "  Cost saved: $" . number_format($savedCost, 2) . "\n";
```

## Testing the Integration

Create `test-integration.php` to verify everything works:

```php
<?php
require_once 'bootstrap.php';

use HelgeSverre\Toon\Toon;

class IntegrationTest {
    private $passed = 0;
    private $failed = 0;
    private $tests = [];

    public function test(string $name, callable $testFunc): void {
        try {
            $testFunc();
            $this->passed++;
            $this->tests[] = ['name' => $name, 'status' => 'PASS', 'error' => null];
            echo "✓ $name\n";
        } catch (Exception $e) {
            $this->failed++;
            $this->tests[] = ['name' => $name, 'status' => 'FAIL', 'error' => $e->getMessage()];
            echo "✗ $name: {$e->getMessage()}\n";
        }
    }

    public function summary(): void {
        echo "\n=== Test Summary ===\n";
        echo "Passed: {$this->passed}\n";
        echo "Failed: {$this->failed}\n";
        echo "Total: " . ($this->passed + $this->failed) . "\n";

        if ($this->failed > 0) {
            echo "\nFailed tests:\n";
            foreach ($this->tests as $test) {
                if ($test['status'] === 'FAIL') {
                    echo "  - {$test['name']}: {$test['error']}\n";
                }
            }
        }
    }
}

$tester = new IntegrationTest();

echo "=== OpenAI + TOON Integration Tests ===\n\n";

$tester->test('TOON encodes simple data', function() {
    $data = ['name' => 'test', 'value' => 123];
    $encoded = Toon::encode($data);
    assert(strpos($encoded, 'name: test') !== false);
    assert(strpos($encoded, 'value: 123') !== false);
});

$tester->test('TOON handles nested structures', function() {
    $data = ['user' => ['id' => 1, 'profile' => ['name' => 'Alice']]];
    $encoded = Toon::encode($data);
    assert(strpos($encoded, 'user:') !== false);
    assert(strpos($encoded, '  profile:') !== false);
});

$tester->test('TOON formats arrays efficiently', function() {
    $data = ['items' => ['a', 'b', 'c']];
    $encoded = Toon::encode($data);
    assert(strpos($encoded, 'items[3]: a,b,c') !== false);
});

$tester->test('TOON is more compact than JSON', function() {
    $data = [
        'users' => [
            ['id' => 1, 'name' => 'Alice', 'active' => true],
            ['id' => 2, 'name' => 'Bob', 'active' => false]
        ]
    ];

    $json = json_encode($data);
    $toon = Toon::encode($data);

    assert(strlen($toon) < strlen($json), 'TOON should be smaller than JSON');
});

$tester->test('Message formatter works correctly', function() {
    $messages = [
        ['role' => 'user', 'content' => 'Test', 'data' => ['key' => 'value']]
    ];

    $formatted = MessageFormatter::formatConversation($messages);
    assert(count($formatted) === 1);
    assert(strpos($formatted[0]['content'], 'key: value') !== false);
});

$tester->test('Function result formatting', function() {
    FunctionCallOptimizer::init();
    $result = ['status' => 'success', 'data' => [1, 2, 3]];
    $formatted = FunctionCallOptimizer::formatFunctionResult($result);

    assert(strpos($formatted, 'status: success') !== false);
    assert(strpos($formatted, 'data[3]: 1,2,3') !== false);
});

$tester->test('Cost calculator accuracy', function() {
    $cost = TokenCostCalculator::calculate('gpt-3.5-turbo', 1000, 500);

    assert($cost['input_cost'] === 0.0005);
    assert($cost['output_cost'] === 0.00075);
    assert($cost['total_cost'] === 0.00125);
});

$tester->summary();
```

## Troubleshooting

### Common Issues and Solutions

1. **API Key Not Working**
   - Verify key starts with `sk-`
   - Check API key permissions
   - Ensure billing is set up on OpenAI account

2. **Token Count Mismatch**
   - Use tiktoken library for accurate counts
   - Remember TOON adds metadata (lengths, headers)
   - Consider whitespace in calculations

3. **Streaming Not Working**
   - Ensure using StreamedResponse properly
   - Check PHP output buffering settings
   - Verify network allows SSE connections

4. **Function Calling Errors**
   - Validate function descriptions match OpenAI schema
   - Ensure TOON output is properly escaped in responses
   - Check tool_call_id matches in responses

5. **Memory Issues with Large Data**
   - Use streaming for large responses
   - Paginate large datasets
   - Consider chunking data processing

## Next Steps

You've mastered OpenAI integration with TOON! Continue to:

1. **Tutorial 3**: Build a complete Laravel application with Prism
2. **Tutorial 4**: Deep dive into token optimization strategies
3. **Tutorial 5**: Implement RAG systems with vector stores

### Key Takeaways

- TOON reduces token usage by 30-60% versus JSON
- Function calling benefits significantly from TOON formatting
- Streaming responses work seamlessly with TOON
- Production implementations need caching and retry logic
- Real cost savings scale with API usage volume

### Additional Resources

- [OpenAI PHP Client Docs](https://github.com/openai-php/client)
- [OpenAI API Reference](https://platform.openai.com/docs/api-reference)
- [Token Counting Guide](https://platform.openai.com/tokenizer)
- [TOON Repository](https://github.com/helgesverre/toon)