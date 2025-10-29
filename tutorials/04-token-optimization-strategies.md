# Reducing LLM Costs: TOON Token Optimization Strategies

**Difficulty**: Advanced
**Time to Complete**: 20-25 minutes
**PHP Version**: 8.1+

## What You'll Build

A comprehensive token optimization system that:
- Analyzes token usage patterns across different data types
- Implements strategic TOON encoding for maximum savings
- Optimizes RAG workflows and document processing
- Builds a token budget management system
- Creates real-time cost tracking dashboards
- Scales optimization strategies for enterprise usage

## What You'll Learn

- Understanding token economics in depth
- Identifying optimization opportunities in your data
- Applying TOON strategically for different use cases
- Optimizing RAG and document-heavy workflows
- Measuring and tracking savings at scale
- Building cost-effective AI applications

## Prerequisites

- Completed Tutorials 1-2 (TOON basics and OpenAI integration)
- Understanding of LLM token pricing models
- Basic knowledge of data structures and algorithms
- Familiarity with caching strategies
- Experience with performance optimization

## Introduction

Token costs can quickly escalate in production LLM applications. A single RAG query might consume 10,000+ tokens, costing $0.02-0.30 depending on the model. Multiply this by thousands of daily requests, and costs become substantial.

TOON's strategic application can reduce these costs by 30-60% without sacrificing functionality. This tutorial teaches you how to identify optimization opportunities, implement strategic encoding, and measure real-world impact.

## Step 1: Understanding Token Economics

Create `token-economics.php` to understand the fundamentals:

```php
<?php
require_once 'vendor/autoload.php';

use HelgeSverre\Toon\Toon;
use HelgeSverre\Toon\EncodeOptions;

class TokenEconomicsAnalyzer {
    private array $modelPricing = [
        // Pricing per 1M tokens (as of 2025)
        'gpt-3.5-turbo' => ['input' => 0.50, 'output' => 1.50],
        'gpt-4' => ['input' => 30.00, 'output' => 60.00],
        'gpt-4-turbo' => ['input' => 10.00, 'output' => 30.00],
        'claude-3-opus' => ['input' => 15.00, 'output' => 75.00],
        'claude-3-sonnet' => ['input' => 3.00, 'output' => 15.00],
        'gemini-pro' => ['input' => 0.50, 'output' => 1.50],
    ];

    private array $metrics = [];

    /**
     * Analyze token usage for different data structures
     */
    public function analyzeDataStructure(array $data, string $label): array {
        $json = json_encode($data, JSON_PRETTY_PRINT);
        $jsonCompact = json_encode($data);
        $toon = Toon::encode($data);
        $toonOptimized = $this->optimizedEncode($data);

        // Estimate tokens (using GPT-style tokenization approximation)
        $jsonTokens = $this->estimateTokens($json);
        $jsonCompactTokens = $this->estimateTokens($jsonCompact);
        $toonTokens = $this->estimateTokens($toon);
        $toonOptimizedTokens = $this->estimateTokens($toonOptimized);

        $analysis = [
            'label' => $label,
            'formats' => [
                'json_pretty' => [
                    'size' => strlen($json),
                    'tokens' => $jsonTokens,
                    'sample' => substr($json, 0, 100) . '...'
                ],
                'json_compact' => [
                    'size' => strlen($jsonCompact),
                    'tokens' => $jsonCompactTokens,
                    'sample' => substr($jsonCompact, 0, 100) . '...'
                ],
                'toon' => [
                    'size' => strlen($toon),
                    'tokens' => $toonTokens,
                    'sample' => substr($toon, 0, 100) . '...'
                ],
                'toon_optimized' => [
                    'size' => strlen($toonOptimized),
                    'tokens' => $toonOptimizedTokens,
                    'sample' => substr($toonOptimized, 0, 100) . '...'
                ]
            ],
            'savings' => [
                'vs_json_pretty' => round((1 - $toonTokens / $jsonTokens) * 100, 1),
                'vs_json_compact' => round((1 - $toonTokens / $jsonCompactTokens) * 100, 1),
                'with_optimization' => round((1 - $toonOptimizedTokens / $jsonTokens) * 100, 1)
            ],
            'cost_impact' => $this->calculateCostImpact($jsonTokens, $toonTokens)
        ];

        $this->metrics[] = $analysis;
        return $analysis;
    }

    /**
     * Optimized TOON encoding with strategic choices
     */
    private function optimizedEncode(array $data): string {
        // Strategy 1: Use minimal indentation
        $options = new EncodeOptions(indent: 1);

        // Strategy 2: Pre-process data to remove unnecessary fields
        $optimized = $this->preprocessData($data);

        return Toon::encode($optimized, $options);
    }

    /**
     * Preprocess data for optimal encoding
     */
    private function preprocessData(array $data): array {
        $processed = [];

        foreach ($data as $key => $value) {
            // Skip null values
            if ($value === null) {
                continue;
            }

            // Skip empty arrays
            if (is_array($value) && empty($value)) {
                continue;
            }

            // Shorten long strings if they're not critical
            if (is_string($value) && strlen($value) > 1000) {
                $processed[$key] = substr($value, 0, 997) . '...';
                continue;
            }

            // Recursively process nested arrays
            if (is_array($value)) {
                $processed[$key] = $this->preprocessData($value);
            } else {
                $processed[$key] = $value;
            }
        }

        return $processed;
    }

    /**
     * Estimate token count (GPT-style approximation)
     */
    private function estimateTokens(string $text): int {
        // More accurate estimation based on OpenAI's tokenization patterns
        $charCount = strlen($text);
        $wordCount = str_word_count($text);
        $specialChars = preg_match_all('/[{}[\]":,]/', $text);

        // GPT models average ~4 characters per token
        // But JSON special characters often tokenize individually
        $baseTokens = ceil($charCount / 4);
        $adjustment = $specialChars * 0.5; // Special chars add overhead

        return (int) ($baseTokens + $adjustment);
    }

    /**
     * Calculate cost impact across different models
     */
    private function calculateCostImpact(int $jsonTokens, int $toonTokens): array {
        $tokensSaved = $jsonTokens - $toonTokens;
        $impact = [];

        foreach ($this->modelPricing as $model => $pricing) {
            $costPerRequest = [
                'json' => ($jsonTokens / 1000000) * $pricing['input'],
                'toon' => ($toonTokens / 1000000) * $pricing['input'],
                'saved' => ($tokensSaved / 1000000) * $pricing['input']
            ];

            $impact[$model] = [
                'per_request' => round($costPerRequest['saved'], 6),
                'per_1k_requests' => round($costPerRequest['saved'] * 1000, 3),
                'per_1m_requests' => round($costPerRequest['saved'] * 1000000, 2)
            ];
        }

        return $impact;
    }

    /**
     * Generate comprehensive report
     */
    public function generateReport(): string {
        $report = "=== Token Economics Analysis Report ===\n\n";

        foreach ($this->metrics as $metric) {
            $report .= "Dataset: {$metric['label']}\n";
            $report .= str_repeat('-', 40) . "\n";

            foreach ($metric['formats'] as $format => $data) {
                $report .= sprintf(
                    "%-15s: %6d tokens (%d bytes)\n",
                    $format,
                    $data['tokens'],
                    $data['size']
                );
            }

            $report .= "\nSavings:\n";
            $report .= "  vs JSON Pretty: {$metric['savings']['vs_json_pretty']}%\n";
            $report .= "  vs JSON Compact: {$metric['savings']['vs_json_compact']}%\n";
            $report .= "  With Optimization: {$metric['savings']['with_optimization']}%\n";

            $report .= "\nCost Impact (per million requests):\n";
            foreach ($metric['cost_impact'] as $model => $impact) {
                $report .= sprintf(
                    "  %-15s: $%.2f saved\n",
                    $model,
                    $impact['per_1m_requests']
                );
            }

            $report .= "\n";
        }

        return $report;
    }
}

// Run analysis on different data types
$analyzer = new TokenEconomicsAnalyzer();

// 1. User profile data (common in personalization)
$userProfile = [
    'user_id' => 'usr_abc123def456',
    'personal_info' => [
        'first_name' => 'Alexandra',
        'last_name' => 'Johnson',
        'email' => 'alex.johnson@example.com',
        'phone' => '+1-555-0123',
        'date_of_birth' => '1985-03-15',
        'address' => [
            'street' => '123 Main Street',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '94105',
            'country' => 'USA'
        ]
    ],
    'preferences' => [
        'language' => 'en-US',
        'timezone' => 'America/Los_Angeles',
        'currency' => 'USD',
        'notifications' => [
            'email' => true,
            'sms' => false,
            'push' => true,
            'frequency' => 'daily'
        ]
    ],
    'account_data' => [
        'created_at' => '2020-01-15T10:30:00Z',
        'last_login' => '2025-01-20T15:45:00Z',
        'subscription_tier' => 'premium',
        'credits_remaining' => 5000,
        'usage_this_month' => 3247
    ]
];

$analyzer->analyzeDataStructure($userProfile, 'User Profile');

// 2. E-commerce transaction (common in retail AI)
$transaction = [
    'transaction_id' => 'TXN-2025-01-20-487293',
    'timestamp' => '2025-01-20T14:32:18Z',
    'customer' => [
        'id' => 'CUST-98234',
        'name' => 'Robert Smith',
        'loyalty_tier' => 'gold',
        'lifetime_value' => 4523.67
    ],
    'items' => [
        ['sku' => 'LAPTOP-X1', 'name' => 'UltraBook Pro 15', 'quantity' => 1, 'price' => 1899.99, 'category' => 'Electronics'],
        ['sku' => 'MOUSE-W5', 'name' => 'Wireless Mouse', 'quantity' => 2, 'price' => 49.99, 'category' => 'Accessories'],
        ['sku' => 'USB-C-10', 'name' => 'USB-C Cable 10ft', 'quantity' => 3, 'price' => 19.99, 'category' => 'Accessories'],
        ['sku' => 'STAND-ADJ', 'name' => 'Adjustable Laptop Stand', 'quantity' => 1, 'price' => 89.99, 'category' => 'Accessories']
    ],
    'payment' => [
        'method' => 'credit_card',
        'last_four' => '4532',
        'processor' => 'stripe',
        'status' => 'completed'
    ],
    'shipping' => [
        'method' => 'express',
        'address' => '456 Oak Avenue, Portland, OR 97201',
        'tracking_number' => 'TRK-ABC-123-456',
        'estimated_delivery' => '2025-01-22'
    ],
    'totals' => [
        'subtotal' => 2149.93,
        'tax' => 193.49,
        'shipping' => 15.99,
        'discount' => -50.00,
        'total' => 2309.41
    ]
];

$analyzer->analyzeDataStructure($transaction, 'E-commerce Transaction');

// 3. Analytics data (common in business intelligence)
$analytics = [
    'period' => ['start' => '2025-01-01', 'end' => '2025-01-20'],
    'metrics' => [
        'visitors' => ['total' => 125847, 'unique' => 89234, 'returning' => 36613],
        'pageviews' => ['total' => 458921, 'per_visitor' => 3.65],
        'engagement' => [
            'avg_session_duration' => 186,
            'bounce_rate' => 42.3,
            'pages_per_session' => 4.2
        ]
    ],
    'top_pages' => [
        ['path' => '/', 'views' => 98234, 'unique_views' => 67123, 'avg_time' => 45, 'bounce_rate' => 38.2],
        ['path' => '/products', 'views' => 67123, 'unique_views' => 45234, 'avg_time' => 120, 'bounce_rate' => 25.1],
        ['path' => '/about', 'views' => 34521, 'unique_views' => 28934, 'avg_time' => 38, 'bounce_rate' => 55.3],
        ['path' => '/contact', 'views' => 12890, 'unique_views' => 11234, 'avg_time' => 95, 'bounce_rate' => 15.2],
        ['path' => '/blog', 'views' => 45678, 'unique_views' => 34567, 'avg_time' => 234, 'bounce_rate' => 35.7]
    ],
    'conversions' => [
        'goals' => [
            ['name' => 'Purchase', 'completions' => 3847, 'rate' => 3.06, 'value' => 284739.50],
            ['name' => 'Signup', 'completions' => 8923, 'rate' => 7.09, 'value' => 0],
            ['name' => 'Download', 'completions' => 5612, 'rate' => 4.46, 'value' => 0]
        ]
    ]
];

$analyzer->analyzeDataStructure($analytics, 'Analytics Dashboard');

// 4. Log entries (common in debugging/monitoring)
$logs = [
    'service' => 'api-gateway',
    'environment' => 'production',
    'entries' => []
];

for ($i = 0; $i < 20; $i++) {
    $logs['entries'][] = [
        'timestamp' => date('Y-m-d H:i:s', time() - rand(0, 3600)),
        'level' => ['INFO', 'WARNING', 'ERROR', 'DEBUG'][rand(0, 3)],
        'message' => 'Sample log message ' . $i,
        'context' => [
            'request_id' => 'REQ-' . bin2hex(random_bytes(8)),
            'user_id' => rand(1000, 9999),
            'ip' => rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255),
            'duration_ms' => rand(10, 500)
        ]
    ];
}

$analyzer->analyzeDataStructure($logs, 'Application Logs');

// Generate and display report
echo $analyzer->generateReport();
```

## Step 2: Strategic Optimization Patterns

Create `optimization-patterns.php`:

```php
<?php
require_once 'vendor/autoload.php';

use HelgeSverre\Toon\Toon;
use HelgeSverre\Toon\EncodeOptions;

class OptimizationPatterns {

    /**
     * Pattern 1: Selective Field Inclusion
     * Only include fields that the LLM actually needs
     */
    public function selectiveInclusion(array $fullData, array $requiredFields): array {
        $before = Toon::encode($fullData);

        // Filter to only required fields
        $filtered = array_intersect_key($fullData, array_flip($requiredFields));

        $after = Toon::encode($filtered);

        return [
            'pattern' => 'Selective Field Inclusion',
            'before_size' => strlen($before),
            'after_size' => strlen($after),
            'reduction' => round((1 - strlen($after) / strlen($before)) * 100, 1),
            'example_before' => substr($before, 0, 200),
            'example_after' => $after
        ];
    }

    /**
     * Pattern 2: Data Summarization
     * Summarize verbose data before encoding
     */
    public function dataSummarization(array $verboseData): array {
        $before = Toon::encode($verboseData);

        // Summarize the data
        $summarized = $this->summarizeData($verboseData);

        $after = Toon::encode($summarized);

        return [
            'pattern' => 'Data Summarization',
            'before_size' => strlen($before),
            'after_size' => strlen($after),
            'reduction' => round((1 - strlen($after) / strlen($before)) * 100, 1),
            'technique' => 'Aggregate and summarize verbose arrays'
        ];
    }

    /**
     * Pattern 3: Reference Compression
     * Replace repeated values with references
     */
    public function referenceCompression(array $data): array {
        $before = Toon::encode($data);

        // Identify repeated values
        $compressed = $this->compressReferences($data);

        $after = Toon::encode($compressed);

        return [
            'pattern' => 'Reference Compression',
            'before_size' => strlen($before),
            'after_size' => strlen($after),
            'reduction' => round((1 - strlen($after) / strlen($before)) * 100, 1),
            'technique' => 'Replace repeated values with short references'
        ];
    }

    /**
     * Pattern 4: Hierarchical Encoding
     * Encode data hierarchically, sending only what's needed
     */
    public function hierarchicalEncoding(array $data): array {
        // Level 1: Summary only
        $summary = [
            'total_items' => count($data['items'] ?? []),
            'total_value' => array_sum(array_column($data['items'] ?? [], 'value')),
            'categories' => array_unique(array_column($data['items'] ?? [], 'category'))
        ];

        // Level 2: Key details
        $keyDetails = array_merge($summary, [
            'top_items' => array_slice($data['items'] ?? [], 0, 3)
        ]);

        // Level 3: Full data
        $fullData = $data;

        return [
            'pattern' => 'Hierarchical Encoding',
            'levels' => [
                'summary' => [
                    'size' => strlen(Toon::encode($summary)),
                    'data' => Toon::encode($summary)
                ],
                'key_details' => [
                    'size' => strlen(Toon::encode($keyDetails)),
                    'data' => Toon::encode($keyDetails)
                ],
                'full' => [
                    'size' => strlen(Toon::encode($fullData)),
                    'data' => substr(Toon::encode($fullData), 0, 200) . '...'
                ]
            ],
            'strategy' => 'Send minimal data first, expand as needed'
        ];
    }

    /**
     * Pattern 5: Template-Based Encoding
     * Use templates for common structures
     */
    public function templateBasedEncoding(array $data, string $template): array {
        $before = Toon::encode($data);

        // Apply template transformation
        $templated = $this->applyTemplate($data, $template);

        $after = $templated; // Already a string from template

        return [
            'pattern' => 'Template-Based Encoding',
            'before_size' => strlen($before),
            'after_size' => strlen($after),
            'reduction' => round((1 - strlen($after) / strlen($before)) * 100, 1),
            'template' => $template,
            'result' => $after
        ];
    }

    /**
     * Pattern 6: Streaming Chunks
     * Break large data into streamable chunks
     */
    public function streamingChunks(array $largeData, int $chunkSize = 10): array {
        $fullEncoded = Toon::encode($largeData);

        $chunks = [];
        $items = $largeData['items'] ?? [];

        for ($i = 0; $i < count($items); $i += $chunkSize) {
            $chunk = array_slice($items, $i, $chunkSize);
            $chunks[] = [
                'chunk_id' => floor($i / $chunkSize) + 1,
                'size' => strlen(Toon::encode($chunk)),
                'items' => count($chunk)
            ];
        }

        return [
            'pattern' => 'Streaming Chunks',
            'full_size' => strlen($fullEncoded),
            'chunks' => $chunks,
            'strategy' => 'Stream data in digestible chunks to stay within token limits'
        ];
    }

    // Helper methods
    private function summarizeData(array $data): array {
        if (!isset($data['entries'])) {
            return $data;
        }

        $summary = [
            'entry_count' => count($data['entries']),
            'date_range' => [
                'start' => $data['entries'][0]['timestamp'] ?? null,
                'end' => end($data['entries'])['timestamp'] ?? null
            ],
            'level_distribution' => []
        ];

        foreach ($data['entries'] as $entry) {
            $level = $entry['level'] ?? 'unknown';
            $summary['level_distribution'][$level] =
                ($summary['level_distribution'][$level] ?? 0) + 1;
        }

        return $summary;
    }

    private function compressReferences(array $data): array {
        $refs = [];
        $refCounter = 0;

        $compressed = $this->replaceWithRefs($data, $refs, $refCounter);

        if (!empty($refs)) {
            $compressed['_refs'] = $refs;
        }

        return $compressed;
    }

    private function replaceWithRefs($value, &$refs, &$refCounter) {
        if (!is_array($value)) {
            // Check if this value appears multiple times
            $serialized = serialize($value);
            if (strlen($serialized) > 50) { // Only for larger values
                foreach ($refs as $refId => $refValue) {
                    if ($refValue === $value) {
                        return "@$refId";
                    }
                }

                if ($refCounter < 10) { // Limit references
                    $refId = "R$refCounter";
                    $refs[$refId] = $value;
                    $refCounter++;
                    return "@$refId";
                }
            }
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = $this->replaceWithRefs($item, $refs, $refCounter);
        }
        return $result;
    }

    private function applyTemplate(array $data, string $template): string {
        // Simple template replacement
        $result = $template;

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = Toon::encode($value);
            }
            $result = str_replace("{{$key}}", $value, $result);
        }

        return $result;
    }
}

// Demonstrate optimization patterns
$optimizer = new OptimizationPatterns();

echo "=== TOON Optimization Patterns ===\n\n";

// Sample dataset
$sampleData = [
    'transaction_id' => 'TXN-2025-98234',
    'customer_id' => 'CUST-45678',
    'customer_name' => 'John Doe',
    'customer_email' => 'john.doe@example.com',
    'customer_phone' => '+1-555-0123',
    'customer_address' => '123 Main St, City, State 12345',
    'items' => [
        ['id' => 1, 'name' => 'Product A', 'category' => 'Electronics', 'value' => 99.99],
        ['id' => 2, 'name' => 'Product B', 'category' => 'Electronics', 'value' => 149.99],
        ['id' => 3, 'name' => 'Product C', 'category' => 'Accessories', 'value' => 29.99],
        ['id' => 4, 'name' => 'Product D', 'category' => 'Electronics', 'value' => 199.99],
        ['id' => 5, 'name' => 'Product E', 'category' => 'Accessories', 'value' => 19.99],
    ],
    'metadata' => [
        'source' => 'web',
        'ip_address' => '192.168.1.1',
        'user_agent' => 'Mozilla/5.0...',
        'session_id' => 'sess_abc123def456ghi789',
        'timestamp' => '2025-01-20T10:30:00Z'
    ]
];

// Pattern 1: Selective Inclusion
$result1 = $optimizer->selectiveInclusion(
    $sampleData,
    ['transaction_id', 'customer_id', 'items']
);
echo "Pattern 1: {$result1['pattern']}\n";
echo "Reduction: {$result1['reduction']}%\n";
echo "Strategy: Only include fields needed for the specific LLM task\n\n";

// Pattern 2: Data Summarization
$logsData = [
    'entries' => array_map(function($i) {
        return [
            'timestamp' => date('Y-m-d H:i:s', time() - $i * 60),
            'level' => ['INFO', 'WARNING', 'ERROR'][$i % 3],
            'message' => "Log entry $i with some detailed information",
            'metadata' => ['request_id' => "req_$i", 'duration' => rand(10, 1000)]
        ];
    }, range(0, 99))
];

$result2 = $optimizer->dataSummarization($logsData);
echo "Pattern 2: {$result2['pattern']}\n";
echo "Reduction: {$result2['reduction']}%\n";
echo "Strategy: {$result2['technique']}\n\n";

// Pattern 3: Reference Compression
$repetitiveData = [
    'records' => array_map(function($i) {
        return [
            'id' => $i,
            'status' => 'active', // Repeated value
            'type' => 'standard', // Repeated value
            'config' => [
                'enabled' => true,
                'version' => '1.0.0',
                'region' => 'us-west-2'
            ], // Repeated structure
            'value' => rand(100, 1000)
        ];
    }, range(1, 10))
];

$result3 = $optimizer->referenceCompression($repetitiveData);
echo "Pattern 3: {$result3['pattern']}\n";
echo "Reduction: {$result3['reduction']}%\n";
echo "Strategy: {$result3['technique']}\n\n";

// Pattern 4: Hierarchical Encoding
$result4 = $optimizer->hierarchicalEncoding($sampleData);
echo "Pattern 4: {$result4['pattern']}\n";
echo "Strategy: {$result4['strategy']}\n";
echo "Summary size: {$result4['levels']['summary']['size']} bytes\n";
echo "Details size: {$result4['levels']['key_details']['size']} bytes\n";
echo "Full size: {$result4['levels']['full']['size']} bytes\n\n";

// Pattern 5: Template-Based Encoding
$template = "Transaction {transaction_id} by customer {customer_id} with {items} items";
$result5 = $optimizer->templateBasedEncoding($sampleData, $template);
echo "Pattern 5: {$result5['pattern']}\n";
echo "Reduction: {$result5['reduction']}%\n";
echo "Result: {$result5['result']}\n\n";

// Pattern 6: Streaming Chunks
$largeData = [
    'items' => array_map(function($i) {
        return ['id' => $i, 'data' => "Item $i data"];
    }, range(1, 100))
];

$result6 = $optimizer->streamingChunks($largeData, 20);
echo "Pattern 6: {$result6['pattern']}\n";
echo "Strategy: {$result6['strategy']}\n";
echo "Full size: {$result6['full_size']} bytes\n";
echo "Chunks: " . count($result6['chunks']) . " chunks\n";
foreach ($result6['chunks'] as $chunk) {
    echo "  Chunk {$chunk['chunk_id']}: {$chunk['items']} items, {$chunk['size']} bytes\n";
}
```

## Step 3: RAG Workflow Optimization

Create `rag-optimization.php`:

```php
<?php
require_once 'vendor/autoload.php';

use HelgeSverre\Toon\Toon;
use HelgeSverre\Toon\EncodeOptions;

class RAGOptimizer {
    private array $documents = [];
    private array $embeddings = [];
    private array $metrics = [];

    /**
     * Optimize document chunks for RAG
     */
    public function optimizeDocumentChunks(string $content, int $chunkSize = 500): array {
        // Split content into chunks
        $chunks = $this->createChunks($content, $chunkSize);

        $optimizedChunks = [];
        $totalOriginalSize = 0;
        $totalOptimizedSize = 0;

        foreach ($chunks as $i => $chunk) {
            // Original format (typical RAG approach)
            $original = [
                'chunk_id' => $i,
                'content' => $chunk['text'],
                'metadata' => [
                    'source' => 'document.pdf',
                    'page' => $chunk['page'],
                    'position' => $chunk['position'],
                    'char_count' => strlen($chunk['text']),
                    'word_count' => str_word_count($chunk['text'])
                ]
            ];

            // Optimized format with TOON
            $optimized = [
                'id' => $i,
                'text' => $chunk['text'],
                'meta' => [
                    'page' => $chunk['page'],
                    'pos' => $chunk['position']
                ]
            ];

            $originalJson = json_encode($original);
            $optimizedToon = Toon::encode($optimized);

            $totalOriginalSize += strlen($originalJson);
            $totalOptimizedSize += strlen($optimizedToon);

            $optimizedChunks[] = [
                'chunk_id' => $i,
                'original_size' => strlen($originalJson),
                'optimized_size' => strlen($optimizedToon),
                'savings' => round((1 - strlen($optimizedToon) / strlen($originalJson)) * 100, 1),
                'data' => $optimized
            ];
        }

        return [
            'chunks' => $optimizedChunks,
            'summary' => [
                'total_chunks' => count($chunks),
                'total_original_size' => $totalOriginalSize,
                'total_optimized_size' => $totalOptimizedSize,
                'total_savings' => round((1 - $totalOptimizedSize / $totalOriginalSize) * 100, 1)
            ]
        ];
    }

    /**
     * Optimize retrieval results
     */
    public function optimizeRetrievalResults(array $results, int $topK = 5): array {
        // Typical RAG retrieval result
        $fullResults = array_map(function($result, $i) {
            return [
                'document_id' => $result['doc_id'],
                'chunk_id' => $result['chunk_id'],
                'score' => $result['score'],
                'content' => $result['content'],
                'metadata' => $result['metadata'],
                'highlights' => $result['highlights'] ?? [],
                'related_chunks' => $result['related'] ?? []
            ];
        }, $results, array_keys($results));

        // Optimized results - only what's needed for the LLM
        $optimizedResults = array_slice(array_map(function($result) {
            return [
                'score' => round($result['score'], 3),
                'content' => $result['content'],
                'source' => $result['metadata']['source'] ?? 'unknown'
            ];
        }, $results), 0, $topK);

        $fullJson = json_encode($fullResults, JSON_PRETTY_PRINT);
        $optimizedToon = Toon::encode($optimizedResults);

        return [
            'full_size' => strlen($fullJson),
            'optimized_size' => strlen($optimizedToon),
            'reduction' => round((1 - strlen($optimizedToon) / strlen($fullJson)) * 100, 1),
            'top_k' => $topK,
            'sample_output' => substr($optimizedToon, 0, 500)
        ];
    }

    /**
     * Optimize embedding metadata
     */
    public function optimizeEmbeddingMetadata(array $documents): array {
        $results = [];

        foreach ($documents as $doc) {
            // Traditional approach - store everything
            $traditional = [
                'id' => $doc['id'],
                'embedding' => $doc['embedding'], // 1536-dimensional vector
                'metadata' => [
                    'title' => $doc['title'],
                    'author' => $doc['author'],
                    'date' => $doc['date'],
                    'category' => $doc['category'],
                    'tags' => $doc['tags'],
                    'summary' => $doc['summary'],
                    'url' => $doc['url'],
                    'word_count' => $doc['word_count'],
                    'language' => $doc['language'],
                    'sentiment' => $doc['sentiment']
                ]
            ];

            // Optimized - minimal metadata, retrieve full later if needed
            $optimized = [
                'id' => $doc['id'],
                'embedding' => $doc['embedding'],
                'meta' => $doc['category'] . '|' . substr($doc['title'], 0, 50)
            ];

            $results[] = [
                'doc_id' => $doc['id'],
                'traditional_size' => strlen(json_encode($traditional)),
                'optimized_size' => strlen(json_encode($optimized)),
                'savings' => round((1 - strlen(json_encode($optimized)) / strlen(json_encode($traditional))) * 100, 1)
            ];
        }

        return $results;
    }

    /**
     * Create context window optimization strategy
     */
    public function optimizeContextWindow(array $retrievedChunks, int $maxTokens = 4000): array {
        $strategy = [
            'max_tokens' => $maxTokens,
            'approaches' => []
        ];

        // Approach 1: Include everything (baseline)
        $everything = [];
        $everythingTokens = 0;
        foreach ($retrievedChunks as $chunk) {
            $encoded = json_encode($chunk);
            $tokens = $this->estimateTokens($encoded);
            if ($everythingTokens + $tokens <= $maxTokens) {
                $everything[] = $chunk;
                $everythingTokens += $tokens;
            }
        }

        $strategy['approaches']['everything_json'] = [
            'chunks_included' => count($everything),
            'estimated_tokens' => $everythingTokens,
            'format' => 'JSON'
        ];

        // Approach 2: TOON encoding with full data
        $toonFull = [];
        $toonFullTokens = 0;
        foreach ($retrievedChunks as $chunk) {
            $encoded = Toon::encode($chunk);
            $tokens = $this->estimateTokens($encoded);
            if ($toonFullTokens + $tokens <= $maxTokens) {
                $toonFull[] = $chunk;
                $toonFullTokens += $tokens;
            }
        }

        $strategy['approaches']['toon_full'] = [
            'chunks_included' => count($toonFull),
            'estimated_tokens' => $toonFullTokens,
            'format' => 'TOON',
            'improvement' => count($toonFull) - count($everything)
        ];

        // Approach 3: TOON with selective fields
        $toonOptimized = [];
        $toonOptTokens = 0;
        foreach ($retrievedChunks as $chunk) {
            $optimized = [
                'content' => $chunk['content'],
                'score' => round($chunk['score'], 2)
            ];
            $encoded = Toon::encode($optimized);
            $tokens = $this->estimateTokens($encoded);
            if ($toonOptTokens + $tokens <= $maxTokens) {
                $toonOptimized[] = $optimized;
                $toonOptTokens += $tokens;
            }
        }

        $strategy['approaches']['toon_optimized'] = [
            'chunks_included' => count($toonOptimized),
            'estimated_tokens' => $toonOptTokens,
            'format' => 'TOON + Selective Fields',
            'improvement' => count($toonOptimized) - count($everything)
        ];

        // Approach 4: Summarization + TOON
        $summarized = $this->summarizeChunks($retrievedChunks);
        $summaryTokens = $this->estimateTokens(Toon::encode($summarized));

        $strategy['approaches']['summarized_toon'] = [
            'chunks_included' => 'All (summarized)',
            'estimated_tokens' => $summaryTokens,
            'format' => 'TOON + Summarization',
            'can_fit_all' => $summaryTokens <= $maxTokens
        ];

        return $strategy;
    }

    // Helper methods
    private function createChunks(string $content, int $chunkSize): array {
        $words = explode(' ', $content);
        $chunks = [];
        $currentChunk = [];
        $currentSize = 0;
        $position = 0;

        foreach ($words as $word) {
            $currentChunk[] = $word;
            $currentSize += strlen($word) + 1;

            if ($currentSize >= $chunkSize) {
                $chunks[] = [
                    'text' => implode(' ', $currentChunk),
                    'position' => $position,
                    'page' => floor($position / 3) + 1 // Simulate pages
                ];
                $position++;
                $currentChunk = [];
                $currentSize = 0;
            }
        }

        if (!empty($currentChunk)) {
            $chunks[] = [
                'text' => implode(' ', $currentChunk),
                'position' => $position,
                'page' => floor($position / 3) + 1
            ];
        }

        return $chunks;
    }

    private function estimateTokens(string $text): int {
        return (int) ceil(strlen($text) / 4);
    }

    private function summarizeChunks(array $chunks): array {
        // Simulate summarization
        return [
            'chunk_count' => count($chunks),
            'avg_score' => round(array_sum(array_column($chunks, 'score')) / count($chunks), 3),
            'key_points' => array_slice(array_map(function($chunk) {
                return substr($chunk['content'], 0, 100) . '...';
            }, $chunks), 0, 5)
        ];
    }
}

// Demonstrate RAG optimizations
$ragOptimizer = new RAGOptimizer();

echo "=== RAG Workflow Optimization ===\n\n";

// 1. Document chunking optimization
$sampleDocument = str_repeat("This is a sample document with lots of content that needs to be chunked for RAG processing. ", 100);

echo "1. Document Chunking Optimization\n";
echo str_repeat('-', 40) . "\n";

$chunkResults = $ragOptimizer->optimizeDocumentChunks($sampleDocument, 200);
echo "Total chunks: {$chunkResults['summary']['total_chunks']}\n";
echo "Original size: {$chunkResults['summary']['total_original_size']} bytes\n";
echo "Optimized size: {$chunkResults['summary']['total_optimized_size']} bytes\n";
echo "Savings: {$chunkResults['summary']['total_savings']}%\n\n";

// 2. Retrieval results optimization
$mockRetrievalResults = array_map(function($i) {
    return [
        'doc_id' => "DOC-$i",
        'chunk_id' => "CHUNK-$i",
        'score' => 0.95 - ($i * 0.05),
        'content' => "Retrieved content $i with relevant information about the query topic...",
        'metadata' => [
            'source' => "document_$i.pdf",
            'page' => rand(1, 50),
            'author' => "Author $i",
            'date' => '2025-01-' . str_pad($i, 2, '0', STR_PAD_LEFT)
        ],
        'highlights' => ["highlight 1", "highlight 2"],
        'related' => ["chunk_" . ($i + 1), "chunk_" . ($i + 2)]
    ];
}, range(1, 20));

echo "2. Retrieval Results Optimization\n";
echo str_repeat('-', 40) . "\n";

$retrievalOpt = $ragOptimizer->optimizeRetrievalResults($mockRetrievalResults, 5);
echo "Full size (JSON): {$retrievalOpt['full_size']} bytes\n";
echo "Optimized size (TOON): {$retrievalOpt['optimized_size']} bytes\n";
echo "Reduction: {$retrievalOpt['reduction']}%\n";
echo "Top K: {$retrievalOpt['top_k']}\n\n";

// 3. Embedding metadata optimization
$mockDocuments = array_map(function($i) {
    return [
        'id' => "DOC-$i",
        'embedding' => array_fill(0, 1536, 0.001 * $i), // Mock embedding
        'title' => "Document Title $i: A comprehensive guide to optimization",
        'author' => "Author Name $i",
        'date' => '2025-01-' . str_pad($i, 2, '0', STR_PAD_LEFT),
        'category' => ['Tech', 'Business', 'Science'][$i % 3],
        'tags' => ['ai', 'ml', 'optimization', 'performance'],
        'summary' => "This document covers various aspects of optimization...",
        'url' => "https://example.com/doc/$i",
        'word_count' => rand(1000, 5000),
        'language' => 'en',
        'sentiment' => 'neutral'
    ];
}, range(1, 5));

echo "3. Embedding Metadata Optimization\n";
echo str_repeat('-', 40) . "\n";

$embeddingOpt = $ragOptimizer->optimizeEmbeddingMetadata($mockDocuments);
$avgSavings = array_sum(array_column($embeddingOpt, 'savings')) / count($embeddingOpt);

echo "Documents processed: " . count($embeddingOpt) . "\n";
echo "Average savings: " . round($avgSavings, 1) . "%\n\n";

// 4. Context window optimization
echo "4. Context Window Optimization Strategy\n";
echo str_repeat('-', 40) . "\n";

$contextStrategy = $ragOptimizer->optimizeContextWindow($mockRetrievalResults, 4000);

foreach ($contextStrategy['approaches'] as $name => $approach) {
    echo "\nApproach: " . str_replace('_', ' ', ucfirst($name)) . "\n";
    echo "  Chunks included: {$approach['chunks_included']}\n";
    echo "  Estimated tokens: {$approach['estimated_tokens']}\n";
    echo "  Format: {$approach['format']}\n";
    if (isset($approach['improvement'])) {
        echo "  Additional chunks vs baseline: {$approach['improvement']}\n";
    }
}
```

## Step 4: Building a Token Budget System

Create `token-budget-system.php`:

```php
<?php
require_once 'vendor/autoload.php';

use HelgeSverre\Toon\Toon;

class TokenBudgetManager {
    private int $maxTokensPerRequest;
    private int $maxTokensPerDay;
    private array $usage = [];
    private array $budgets = [];

    public function __construct(int $maxPerRequest = 4000, int $maxPerDay = 1000000) {
        $this->maxTokensPerRequest = $maxPerRequest;
        $this->maxTokensPerDay = $maxPerDay;

        // Initialize budgets for different components
        $this->budgets = [
            'system_prompt' => 500,
            'conversation_history' => 1000,
            'retrieved_context' => 2000,
            'user_input' => 500
        ];
    }

    /**
     * Allocate tokens for a request
     */
    public function allocateTokens(array $components): array {
        $allocation = [];
        $totalNeeded = 0;

        // Calculate needs for each component
        foreach ($components as $name => $data) {
            $jsonTokens = $this->estimateTokens(json_encode($data));
            $toonTokens = $this->estimateTokens(Toon::encode($data));

            $allocation[$name] = [
                'data' => $data,
                'json_tokens' => $jsonTokens,
                'toon_tokens' => $toonTokens,
                'budget' => $this->budgets[$name] ?? 500,
                'fits_budget_json' => $jsonTokens <= ($this->budgets[$name] ?? 500),
                'fits_budget_toon' => $toonTokens <= ($this->budgets[$name] ?? 500)
            ];

            $totalNeeded += $toonTokens;
        }

        // Optimization strategies if over budget
        if ($totalNeeded > $this->maxTokensPerRequest) {
            $allocation = $this->optimizeAllocation($allocation);
        }

        return [
            'allocation' => $allocation,
            'total_tokens' => array_sum(array_column($allocation, 'toon_tokens')),
            'within_budget' => $totalNeeded <= $this->maxTokensPerRequest,
            'optimization_applied' => $totalNeeded > $this->maxTokensPerRequest
        ];
    }

    /**
     * Track daily usage
     */
    public function trackUsage(string $userId, int $tokens): array {
        $date = date('Y-m-d');

        if (!isset($this->usage[$date])) {
            $this->usage[$date] = [];
        }

        if (!isset($this->usage[$date][$userId])) {
            $this->usage[$date][$userId] = 0;
        }

        $this->usage[$date][$userId] += $tokens;

        $dailyTotal = array_sum($this->usage[$date]);
        $userTotal = $this->usage[$date][$userId];

        return [
            'user_id' => $userId,
            'tokens_used' => $tokens,
            'user_total_today' => $userTotal,
            'system_total_today' => $dailyTotal,
            'user_remaining' => max(0, 100000 - $userTotal), // User limit
            'system_remaining' => max(0, $this->maxTokensPerDay - $dailyTotal),
            'at_user_limit' => $userTotal >= 100000,
            'at_system_limit' => $dailyTotal >= $this->maxTokensPerDay
        ];
    }

    /**
     * Get usage report
     */
    public function getUsageReport(): array {
        $report = [
            'daily_limit' => $this->maxTokensPerDay,
            'request_limit' => $this->maxTokensPerRequest,
            'usage_by_date' => []
        ];

        foreach ($this->usage as $date => $users) {
            $report['usage_by_date'][$date] = [
                'total_tokens' => array_sum($users),
                'unique_users' => count($users),
                'avg_per_user' => round(array_sum($users) / count($users)),
                'top_users' => array_slice($users, 0, 5, true)
            ];
        }

        return $report;
    }

    /**
     * Optimize allocation when over budget
     */
    private function optimizeAllocation(array $allocation): array {
        // Priority order for optimization
        $priorities = [
            'retrieved_context' => 1,
            'conversation_history' => 2,
            'user_input' => 3,
            'system_prompt' => 4
        ];

        asort($priorities);

        foreach ($priorities as $component => $priority) {
            if (!isset($allocation[$component])) continue;

            $data = $allocation[$component]['data'];

            // Apply progressive optimization
            if ($priority === 1) {
                // Most aggressive optimization for lowest priority
                $optimized = $this->aggressiveOptimization($data);
            } elseif ($priority === 2) {
                // Moderate optimization
                $optimized = $this->moderateOptimization($data);
            } else {
                // Light optimization for high priority
                $optimized = $this->lightOptimization($data);
            }

            $allocation[$component]['data'] = $optimized;
            $allocation[$component]['toon_tokens'] = $this->estimateTokens(Toon::encode($optimized));
            $allocation[$component]['optimization'] = 'applied';
        }

        return $allocation;
    }

    private function aggressiveOptimization($data): array {
        if (!is_array($data)) return $data;

        // Keep only essential fields
        $essential = ['id', 'content', 'score'];
        $optimized = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $essential) || is_numeric($key)) {
                if (is_array($value) && count($value) > 3) {
                    $optimized[$key] = array_slice($value, 0, 3);
                } else {
                    $optimized[$key] = $value;
                }
            }
        }

        return $optimized;
    }

    private function moderateOptimization($data): array {
        if (!is_array($data)) return $data;

        // Remove metadata and truncate long content
        $optimized = [];

        foreach ($data as $key => $value) {
            if ($key === 'metadata' || $key === 'debug') {
                continue;
            }

            if (is_string($value) && strlen($value) > 500) {
                $optimized[$key] = substr($value, 0, 497) . '...';
            } elseif (is_array($value) && count($value) > 10) {
                $optimized[$key] = array_slice($value, 0, 10);
            } else {
                $optimized[$key] = $value;
            }
        }

        return $optimized;
    }

    private function lightOptimization($data): array {
        if (!is_array($data)) return $data;

        // Only remove null values and empty arrays
        $optimized = [];

        foreach ($data as $key => $value) {
            if ($value !== null && $value !== [] && $value !== '') {
                $optimized[$key] = $value;
            }
        }

        return $optimized;
    }

    private function estimateTokens(string $text): int {
        return (int) ceil(strlen($text) / 4);
    }
}

// Demonstrate token budget system
$budgetManager = new TokenBudgetManager(4000, 1000000);

echo "=== Token Budget Management System ===\n\n";

// Simulate a request with multiple components
$requestComponents = [
    'system_prompt' => [
        'role' => 'You are a helpful assistant specialized in data analysis.',
        'instructions' => [
            'Be concise and clear',
            'Use examples when helpful',
            'Cite sources when available'
        ],
        'capabilities' => ['analysis', 'visualization', 'reporting']
    ],
    'conversation_history' => array_map(function($i) {
        return [
            'role' => $i % 2 == 0 ? 'user' : 'assistant',
            'content' => "Message $i with some content that could be quite long...",
            'timestamp' => time() - (10 - $i) * 60
        ];
    }, range(1, 20)),
    'retrieved_context' => array_map(function($i) {
        return [
            'doc_id' => "DOC-$i",
            'content' => str_repeat("Retrieved document content $i. ", 50),
            'relevance_score' => 0.9 - ($i * 0.05),
            'metadata' => [
                'source' => "source_$i.pdf",
                'page' => $i,
                'date' => '2025-01-' . str_pad($i, 2, '0', STR_PAD_LEFT)
            ]
        ];
    }, range(1, 10)),
    'user_input' => [
        'query' => 'Analyze the sales data for Q4 2024 and provide insights on trends.',
        'context' => [
            'department' => 'Sales',
            'region' => 'North America',
            'time_range' => 'Q4 2024'
        ]
    ]
];

// Allocate tokens
$allocation = $budgetManager->allocateTokens($requestComponents);

echo "Token Allocation Results:\n";
echo str_repeat('-', 40) . "\n";
echo "Total tokens needed: {$allocation['total_tokens']}\n";
echo "Within budget: " . ($allocation['within_budget'] ? 'YES' : 'NO') . "\n";
echo "Optimization applied: " . ($allocation['optimization_applied'] ? 'YES' : 'NO') . "\n\n";

echo "Component Breakdown:\n";
foreach ($allocation['allocation'] as $component => $details) {
    echo "\n$component:\n";
    echo "  JSON tokens: {$details['json_tokens']}\n";
    echo "  TOON tokens: {$details['toon_tokens']}\n";
    echo "  Budget: {$details['budget']}\n";
    echo "  Fits with JSON: " . ($details['fits_budget_json'] ? 'YES' : 'NO') . "\n";
    echo "  Fits with TOON: " . ($details['fits_budget_toon'] ? 'YES' : 'NO') . "\n";
    if (isset($details['optimization'])) {
        echo "  Optimization: {$details['optimization']}\n";
    }
}

// Track usage for multiple users
echo "\n\nUsage Tracking:\n";
echo str_repeat('-', 40) . "\n";

$users = ['user_123', 'user_456', 'user_789'];
foreach ($users as $userId) {
    for ($i = 0; $i < 5; $i++) {
        $tokens = rand(500, 2000);
        $tracking = $budgetManager->trackUsage($userId, $tokens);
    }
}

// Get usage report
$report = $budgetManager->getUsageReport();

echo "Daily limit: " . number_format($report['daily_limit']) . " tokens\n";
echo "Request limit: " . number_format($report['request_limit']) . " tokens\n\n";

foreach ($report['usage_by_date'] as $date => $stats) {
    echo "Date: $date\n";
    echo "  Total tokens: " . number_format($stats['total_tokens']) . "\n";
    echo "  Unique users: {$stats['unique_users']}\n";
    echo "  Average per user: " . number_format($stats['avg_per_user']) . "\n";
    echo "  Top users:\n";
    foreach ($stats['top_users'] as $userId => $tokens) {
        echo "    $userId: " . number_format($tokens) . " tokens\n";
    }
}
```

## Step 5: Real-World Case Study

Create `case-study.php`:

```php
<?php
require_once 'vendor/autoload.php';

use HelgeSverre\Toon\Toon;

class CostAnalysisCaseStudy {
    private array $scenario;
    private array $results = [];

    public function __construct() {
        // Real-world scenario: Customer support chatbot
        $this->scenario = [
            'company' => 'TechCorp Support',
            'daily_conversations' => 5000,
            'avg_messages_per_conversation' => 8,
            'data_per_message' => [
                'customer_context' => 500, // chars
                'product_info' => 800,
                'conversation_history' => 1200,
                'knowledge_base_results' => 2000
            ],
            'llm_model' => 'gpt-3.5-turbo',
            'pricing' => [
                'input' => 0.0005, // per 1K tokens
                'output' => 0.0015
            ]
        ];
    }

    /**
     * Run the complete case study
     */
    public function runAnalysis(): array {
        $this->results['scenario'] = $this->scenario;

        // Calculate baseline (JSON)
        $this->results['baseline'] = $this->calculateBaseline();

        // Calculate with TOON
        $this->results['with_toon'] = $this->calculateWithTOON();

        // Calculate with advanced optimization
        $this->results['optimized'] = $this->calculateOptimized();

        // Generate insights
        $this->results['insights'] = $this->generateInsights();

        // ROI calculation
        $this->results['roi'] = $this->calculateROI();

        return $this->results;
    }

    private function calculateBaseline(): array {
        $dailyMessages = $this->scenario['daily_conversations'] *
                        $this->scenario['avg_messages_per_conversation'];

        $charsPerMessage = array_sum($this->scenario['data_per_message']);
        $tokensPerMessage = ceil($charsPerMessage / 4);

        $dailyInputTokens = $dailyMessages * $tokensPerMessage;
        $dailyOutputTokens = $dailyMessages * 150; // Avg response

        $dailyCost = ($dailyInputTokens / 1000 * $this->scenario['pricing']['input']) +
                    ($dailyOutputTokens / 1000 * $this->scenario['pricing']['output']);

        return [
            'daily_messages' => $dailyMessages,
            'tokens_per_message' => $tokensPerMessage,
            'daily_input_tokens' => $dailyInputTokens,
            'daily_output_tokens' => $dailyOutputTokens,
            'daily_cost' => $dailyCost,
            'monthly_cost' => $dailyCost * 30,
            'yearly_cost' => $dailyCost * 365
        ];
    }

    private function calculateWithTOON(): array {
        $baseline = $this->results['baseline'];

        // TOON reduces tokens by average 40%
        $reductionFactor = 0.6;

        $toonTokensPerMessage = ceil($baseline['tokens_per_message'] * $reductionFactor);
        $dailyInputTokens = $baseline['daily_messages'] * $toonTokensPerMessage;

        $dailyCost = ($dailyInputTokens / 1000 * $this->scenario['pricing']['input']) +
                    ($baseline['daily_output_tokens'] / 1000 * $this->scenario['pricing']['output']);

        return [
            'daily_messages' => $baseline['daily_messages'],
            'tokens_per_message' => $toonTokensPerMessage,
            'daily_input_tokens' => $dailyInputTokens,
            'daily_output_tokens' => $baseline['daily_output_tokens'],
            'daily_cost' => $dailyCost,
            'monthly_cost' => $dailyCost * 30,
            'yearly_cost' => $dailyCost * 365,
            'savings_daily' => $baseline['daily_cost'] - $dailyCost,
            'savings_monthly' => $baseline['monthly_cost'] - ($dailyCost * 30),
            'savings_yearly' => $baseline['yearly_cost'] - ($dailyCost * 365)
        ];
    }

    private function calculateOptimized(): array {
        $baseline = $this->results['baseline'];

        // Advanced optimization: TOON + selective fields + caching
        $reductionFactor = 0.45; // 55% reduction
        $cachingReduction = 0.2; // 20% requests hit cache

        $optimizedTokensPerMessage = ceil($baseline['tokens_per_message'] * $reductionFactor);
        $effectiveMessages = $baseline['daily_messages'] * (1 - $cachingReduction);
        $dailyInputTokens = $effectiveMessages * $optimizedTokensPerMessage;

        $dailyCost = ($dailyInputTokens / 1000 * $this->scenario['pricing']['input']) +
                    ($baseline['daily_output_tokens'] * (1 - $cachingReduction) / 1000 *
                     $this->scenario['pricing']['output']);

        return [
            'daily_messages' => $baseline['daily_messages'],
            'effective_messages' => $effectiveMessages,
            'tokens_per_message' => $optimizedTokensPerMessage,
            'daily_input_tokens' => $dailyInputTokens,
            'daily_output_tokens' => $baseline['daily_output_tokens'] * (1 - $cachingReduction),
            'daily_cost' => $dailyCost,
            'monthly_cost' => $dailyCost * 30,
            'yearly_cost' => $dailyCost * 365,
            'savings_daily' => $baseline['daily_cost'] - $dailyCost,
            'savings_monthly' => $baseline['monthly_cost'] - ($dailyCost * 30),
            'savings_yearly' => $baseline['yearly_cost'] - ($dailyCost * 365),
            'reduction_percentage' => round((1 - $dailyCost / $baseline['daily_cost']) * 100, 1)
        ];
    }

    private function generateInsights(): array {
        $baseline = $this->results['baseline'];
        $toon = $this->results['with_toon'];
        $optimized = $this->results['optimized'];

        return [
            'token_reduction' => [
                'toon_only' => round((1 - $toon['daily_input_tokens'] /
                              $baseline['daily_input_tokens']) * 100, 1),
                'fully_optimized' => round((1 - $optimized['daily_input_tokens'] /
                                    $baseline['daily_input_tokens']) * 100, 1)
            ],
            'cost_reduction' => [
                'toon_only' => round((1 - $toon['daily_cost'] /
                              $baseline['daily_cost']) * 100, 1),
                'fully_optimized' => round((1 - $optimized['daily_cost'] /
                                    $baseline['daily_cost']) * 100, 1)
            ],
            'capacity_increase' => [
                'description' => 'Additional conversations possible with same budget',
                'toon_only' => round($baseline['daily_cost'] / $toon['daily_cost'] *
                              $this->scenario['daily_conversations'] -
                              $this->scenario['daily_conversations']),
                'fully_optimized' => round($baseline['daily_cost'] / $optimized['daily_cost'] *
                                    $this->scenario['daily_conversations'] -
                                    $this->scenario['daily_conversations'])
            ],
            'break_even' => [
                'implementation_hours' => 40,
                'hourly_rate' => 150,
                'implementation_cost' => 6000,
                'days_to_break_even' => round(6000 / $optimized['savings_daily']),
                'months_to_break_even' => round(6000 / $optimized['savings_monthly'], 1)
            ]
        ];
    }

    private function calculateROI(): array {
        $optimized = $this->results['optimized'];
        $implementationCost = 6000;

        return [
            'year_1' => [
                'savings' => $optimized['savings_yearly'],
                'costs' => $implementationCost,
                'net_benefit' => $optimized['savings_yearly'] - $implementationCost,
                'roi_percentage' => round((($optimized['savings_yearly'] - $implementationCost) /
                                  $implementationCost) * 100, 1)
            ],
            'year_2' => [
                'savings' => $optimized['savings_yearly'],
                'costs' => 0,
                'net_benefit' => $optimized['savings_yearly'],
                'cumulative_benefit' => ($optimized['savings_yearly'] * 2) - $implementationCost
            ],
            'year_3' => [
                'savings' => $optimized['savings_yearly'],
                'costs' => 0,
                'net_benefit' => $optimized['savings_yearly'],
                'cumulative_benefit' => ($optimized['savings_yearly'] * 3) - $implementationCost
            ]
        ];
    }

    public function generateReport(): string {
        if (empty($this->results)) {
            $this->runAnalysis();
        }

        $report = <<<REPORT
=== CUSTOMER SUPPORT CHATBOT COST ANALYSIS ===
Company: {$this->scenario['company']}
Scale: {$this->scenario['daily_conversations']} conversations/day

BASELINE (JSON)
--------------
Daily Cost: \${$this->results['baseline']['daily_cost']}
Monthly Cost: \${$this->results['baseline']['monthly_cost']}
Yearly Cost: \${$this->results['baseline']['yearly_cost']}
Tokens per Message: {$this->results['baseline']['tokens_per_message']}

WITH TOON
---------
Daily Cost: \${$this->results['with_toon']['daily_cost']}
Monthly Cost: \${$this->results['with_toon']['monthly_cost']}
Yearly Cost: \${$this->results['with_toon']['yearly_cost']}
Daily Savings: \${$this->results['with_toon']['savings_daily']}
Yearly Savings: \${$this->results['with_toon']['savings_yearly']}

FULLY OPTIMIZED (TOON + Strategies)
-----------------------------------
Daily Cost: \${$this->results['optimized']['daily_cost']}
Monthly Cost: \${$this->results['optimized']['monthly_cost']}
Yearly Cost: \${$this->results['optimized']['yearly_cost']}
Daily Savings: \${$this->results['optimized']['savings_daily']}
Yearly Savings: \${$this->results['optimized']['savings_yearly']}
Cost Reduction: {$this->results['optimized']['reduction_percentage']}%

KEY INSIGHTS
------------
Token Reduction: {$this->results['insights']['token_reduction']['fully_optimized']}%
Cost Reduction: {$this->results['insights']['cost_reduction']['fully_optimized']}%
Additional Capacity: {$this->results['insights']['capacity_increase']['fully_optimized']} more conversations/day
Break-even: {$this->results['insights']['break_even']['days_to_break_even']} days

ROI ANALYSIS
-----------
Year 1 ROI: {$this->results['roi']['year_1']['roi_percentage']}%
Year 1 Net Benefit: \${$this->results['roi']['year_1']['net_benefit']}
3-Year Cumulative Benefit: \${$this->results['roi']['year_3']['cumulative_benefit']}

RECOMMENDATION
-------------
Implementing TOON with optimization strategies will:
1. Reduce token costs by {$this->results['insights']['cost_reduction']['fully_optimized']}%
2. Save \${$this->results['optimized']['savings_yearly']} annually
3. Break even in {$this->results['insights']['break_even']['months_to_break_even']} months
4. Enable {$this->results['insights']['capacity_increase']['fully_optimized']} additional conversations daily

The investment pays for itself quickly and provides substantial long-term savings.
REPORT;

        return $report;
    }
}

// Run the case study
$caseStudy = new CostAnalysisCaseStudy();
$results = $caseStudy->runAnalysis();

echo $caseStudy->generateReport();

// Additional analysis output
echo "\n\n=== DETAILED ANALYSIS ===\n\n";

// Show example optimization
$sampleData = [
    'customer' => [
        'id' => 'CUST-12345',
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'subscription' => 'premium',
        'support_tier' => 'gold'
    ],
    'ticket' => [
        'id' => 'TKT-98765',
        'subject' => 'Cannot access dashboard',
        'priority' => 'high',
        'created_at' => '2025-01-20T10:30:00Z',
        'category' => 'technical'
    ],
    'context' => [
        'last_login' => '2025-01-19T15:00:00Z',
        'browser' => 'Chrome 120',
        'os' => 'Windows 11',
        'error_logs' => [
            ['time' => '10:28:00', 'error' => 'Auth token expired'],
            ['time' => '10:29:00', 'error' => 'Redirect loop detected']
        ]
    ]
];

$json = json_encode($sampleData, JSON_PRETTY_PRINT);
$toon = Toon::encode($sampleData);

echo "Sample Customer Support Data:\n";
echo str_repeat('-', 40) . "\n";
echo "JSON format: " . strlen($json) . " characters\n";
echo "TOON format: " . strlen($toon) . " characters\n";
echo "Reduction: " . round((1 - strlen($toon) / strlen($json)) * 100, 1) . "%\n\n";

echo "TOON output:\n$toon\n";
```

## Testing and Validation

Create `test-optimization.php`:

```php
<?php
require_once 'vendor/autoload.php';

use HelgeSverre\Toon\Toon;

// Test different optimization strategies
$testCases = [
    'small_object' => [
        'id' => 1,
        'name' => 'Test'
    ],
    'medium_array' => range(1, 100),
    'large_nested' => [
        'level1' => [
            'level2' => [
                'level3' => [
                    'data' => array_map(function($i) {
                        return ['id' => $i, 'value' => rand(100, 999)];
                    }, range(1, 50))
                ]
            ]
        ]
    ],
    'mixed_types' => [
        'string' => 'Hello World',
        'number' => 42,
        'float' => 3.14159,
        'boolean' => true,
        'null' => null,
        'array' => [1, 2, 3],
        'object' => ['key' => 'value']
    ]
];

echo "=== Optimization Strategy Validation ===\n\n";

foreach ($testCases as $name => $data) {
    $json = json_encode($data);
    $jsonPretty = json_encode($data, JSON_PRETTY_PRINT);
    $toon = Toon::encode($data);

    $jsonTokens = ceil(strlen($json) / 4);
    $jsonPrettyTokens = ceil(strlen($jsonPretty) / 4);
    $toonTokens = ceil(strlen($toon) / 4);

    echo "Test Case: $name\n";
    echo "  Compact JSON: $jsonTokens tokens\n";
    echo "  Pretty JSON: $jsonPrettyTokens tokens\n";
    echo "  TOON: $toonTokens tokens\n";
    echo "  Savings vs Compact: " . round((1 - $toonTokens / $jsonTokens) * 100, 1) . "%\n";
    echo "  Savings vs Pretty: " . round((1 - $toonTokens / $jsonPrettyTokens) * 100, 1) . "%\n\n";
}

// Validate token counting accuracy
echo "=== Token Counting Validation ===\n";
echo "Note: For production, use tiktoken or the LLM's tokenizer\n";
echo "This uses approximation (4 chars/token) for demonstration\n";
```

## Troubleshooting

### Common Issues and Solutions

1. **Token count inaccuracy**
   - Use tiktoken library for OpenAI models
   - Use model-specific tokenizers
   - Account for special tokens

2. **Over-optimization causing data loss**
   - Test optimization levels thoroughly
   - Keep critical fields in whitelist
   - Implement rollback mechanisms

3. **Cache invalidation issues**
   - Use versioned cache keys
   - Implement TTL strategies
   - Monitor cache hit rates

4. **Budget exceeded despite optimization**
   - Review data preprocessing pipeline
   - Implement request queuing
   - Use model fallbacks (GPT-4 → GPT-3.5)

5. **Performance degradation**
   - Profile encoding operations
   - Implement async processing
   - Use lazy loading for large datasets

## Next Steps

You've mastered token optimization strategies! Continue with:

1. **Tutorial 5**: Build RAG systems with vector stores
2. **Advanced Topics**: Custom tokenizers, model-specific optimizations
3. **Production Deployment**: Scaling strategies, monitoring, alerting

### Key Takeaways

- TOON provides consistent 30-60% token reduction
- Strategic optimization can push savings to 70%+
- Small optimizations compound to significant cost savings
- Token budgeting is essential for production systems
- ROI is typically achieved within 1-3 months

### Additional Resources

- [OpenAI Tokenizer](https://platform.openai.com/tokenizer)
- [Tiktoken Library](https://github.com/openai/tiktoken)
- [TOON Benchmarks](https://github.com/helgesverre/toon/benchmarks)
- [LLM Pricing Calculator](https://openai.com/pricing)