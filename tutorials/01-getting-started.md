# Getting Started with TOON in PHP

**Difficulty**: Beginner
**Time to Complete**: 10-15 minutes
**PHP Version**: 8.1+

## What You'll Build

A complete PHP script that:
- Encodes various data structures using TOON
- Compares token consumption with JSON
- Processes real-world receipt data for LLM validation
- Measures actual token usage and cost reduction

## What You'll Learn

- What TOON is and why it reduces LLM token consumption
- How to install and configure TOON
- Encoding different data types (strings, arrays, objects)
- Comparing token savings versus JSON
- Processing structured data for LLM analysis

## Prerequisites

- PHP 8.1 or higher installed
- Composer package manager
- Basic understanding of PHP arrays and objects

## Introduction

When working with Large Language Models (LLMs), every token counts - literally. Each token costs money and takes up valuable context window space. JSON, while ubiquitous, is verbose with its brackets, braces, and quotes.

TOON (Token-Oriented Object Notation) solves this problem by providing a more compact format that's still human-readable. It can reduce token consumption by 30-60% compared to JSON, which directly translates to cost savings and more efficient use of context windows.

## Step 1: Installation

First, let's create a new project directory and install TOON:

```bash
mkdir toon-tutorial
cd toon-tutorial
composer init --name="tutorial/toon-demo" --type=project --require="helgesverre/toon:^1.0" -n
composer install
```

Verify the installation by creating a test file:

```php
<?php
// test.php
require_once 'vendor/autoload.php';

// Use the helper function
echo toon('Hello TOON!');
```

Run it:

```bash
php test.php
```

Expected output:
```
Hello TOON!
```

## Step 2: Understanding Basic Encoding

Let's explore how TOON encodes different data types. Create a new file `basic-encoding.php`:

```php
<?php
require_once 'vendor/autoload.php';

// Primitive values
echo "=== Primitive Values ===\n";
echo "String: " . toon('hello world') . "\n";        // hello world
echo "Number: " . toon(42) . "\n";                   // 42
echo "Float: " . toon(3.14) . "\n";                  // 3.14
echo "Boolean: " . toon(true) . "\n";                // true
echo "Null: " . toon(null) . "\n";                   // null

// Special string cases (automatic quoting)
echo "\n=== String Quoting ===\n";
echo "Regular: " . toon('hello') . "\n";             // hello
echo "Looks like bool: " . toon('true') . "\n";      // "true"
echo "Looks like number: " . toon('42') . "\n";      // "42"
echo "Has colon: " . toon('key:value') . "\n";       // "key:value"
echo "Empty string: " . toon('') . "\n";             // ""

// Simple arrays
echo "\n=== Simple Arrays ===\n";
$fruits = ['apple', 'banana', 'orange'];
echo "Fruits: " . toon($fruits) . "\n";              // [3]: apple,banana,orange

$numbers = [10, 20, 30, 40, 50];
echo "Numbers: " . toon($numbers) . "\n";            // [5]: 10,20,30,40,50

// Simple objects (associative arrays)
echo "\n=== Objects ===\n";
$person = [
    'name' => 'Alice',
    'age' => 30,
    'active' => true
];
echo "Person:\n" . toon($person) . "\n";
```

Run it:

```bash
php basic-encoding.php
```

Expected output:
```
=== Primitive Values ===
String: hello world
Number: 42
Float: 3.14
Boolean: true
Null: null

=== String Quoting ===
Regular: hello
Looks like bool: "true"
Looks like number: "42"
Has colon: "key:value"
Empty string: ""

=== Simple Arrays ===
Fruits: [3]: apple,banana,orange
Numbers: [5]: 10,20,30,40,50

=== Objects ===
Person:
name: Alice
age: 30
active: true
```

## Step 3: Working with Nested Structures

Now let's handle more complex, nested data structures. Create `nested-structures.php`:

```php
<?php
require_once 'vendor/autoload.php';

// Nested object
$user = [
    'id' => 1001,
    'username' => 'alice_dev',
    'profile' => [
        'firstName' => 'Alice',
        'lastName' => 'Developer',
        'email' => 'alice@example.com',
        'preferences' => [
            'theme' => 'dark',
            'notifications' => true,
            'language' => 'en'
        ]
    ],
    'tags' => ['php', 'laravel', 'vue', 'docker'],
    'stats' => [
        'posts' => 42,
        'followers' => 1337,
        'following' => 256
    ]
];

echo "=== Nested User Object ===\n";
echo toon($user) . "\n\n";

// Array of uniform objects (tabular format)
$products = [
    ['sku' => 'LAPTOP-001', 'name' => 'ThinkPad X1', 'price' => 1299.99, 'stock' => 5],
    ['sku' => 'MOUSE-002', 'name' => 'MX Master 3', 'price' => 99.99, 'stock' => 25],
    ['sku' => 'KEYB-003', 'name' => 'Keychron K2', 'price' => 89.99, 'stock' => 15],
];

echo "=== Tabular Product Data ===\n";
echo "products:\n" . toon($products) . "\n\n";

// Non-uniform array (list format)
$events = [
    [
        'type' => 'login',
        'user' => 'alice',
        'timestamp' => '2025-01-15T10:30:00Z'
    ],
    [
        'type' => 'purchase',
        'user' => 'bob',
        'product' => 'LAPTOP-001',
        'amount' => 1299.99,
        'timestamp' => '2025-01-15T11:00:00Z'
    ],
    [
        'type' => 'logout',
        'user' => 'alice',
        'timestamp' => '2025-01-15T12:00:00Z'
    ]
];

echo "=== Non-uniform Event Log ===\n";
echo "events:\n" . toon($events) . "\n";
```

Run it:

```bash
php nested-structures.php
```

Expected output:
```
=== Nested User Object ===
id: 1001
username: alice_dev
profile:
  firstName: Alice
  lastName: Developer
  email: alice@example.com
  preferences:
    theme: dark
    notifications: true
    language: en
tags[4]: php,laravel,vue,docker
stats:
  posts: 42
  followers: 1337
  following: 256

=== Tabular Product Data ===
products:
[3]{sku,name,price,stock}:
  LAPTOP-001,ThinkPad X1,1299.99,5
  MOUSE-002,MX Master 3,99.99,25
  KEYB-003,Keychron K2,89.99,15

=== Non-uniform Event Log ===
events:
[3]:
  - type: login
    user: alice
    timestamp: "2025-01-15T10:30:00Z"
  - type: purchase
    user: bob
    product: LAPTOP-001
    amount: 1299.99
    timestamp: "2025-01-15T11:00:00Z"
  - type: logout
    user: alice
    timestamp: "2025-01-15T12:00:00Z"
```

## Step 4: Configuration Options

TOON provides configuration options to customize the output. Create `configuration.php`:

```php
<?php
require_once 'vendor/autoload.php';

use HelgeSverre\Toon\EncodeOptions;

$data = [
    'server' => [
        'host' => 'api.example.com',
        'port' => 8080,
        'ssl' => true,
        'endpoints' => ['users', 'posts', 'comments']
    ]
];

echo "=== Default Configuration ===\n";
echo toon($data) . "\n\n";

echo "=== Compact Format ===\n";
echo toon_compact($data) . "\n\n";

echo "=== Readable Format (4 spaces) ===\n";
echo toon_readable($data) . "\n\n";

echo "=== Tabular Format (tab delimiters) ===\n";
$tabData = ['tags' => ['php', 'javascript', 'python', 'ruby']];
echo toon_tabular($tabData) . "\n\n";

echo "=== Custom Options ===\n";
$options = new EncodeOptions(
    indent: 3,
    delimiter: '|'
);
$customData = ['items' => ['apple', 'banana', 'cherry']];
echo toon($customData, $options) . "\n";
```

Run it:

```bash
php configuration.php
```

Expected output:
```
=== Default Configuration ===
server:
  host: api.example.com
  port: 8080
  ssl: true
  endpoints[3]: users,posts,comments

=== Compact Format ===
server:
 host: api.example.com
 port: 8080
 ssl: true
 endpoints[3]: users,posts,comments

=== Readable Format (4 spaces) ===
server:
    host: api.example.com
    port: 8080
    ssl: true
    endpoints[3]: users,posts,comments

=== Tabular Format (tab delimiters) ===
tags[4	]: php	javascript	python	ruby

=== Custom Options ===
items[3|]: apple|banana|cherry
```

## Step 5: Token Comparison with JSON

Let's measure the actual token savings. Create `token-comparison.php`:

```php
<?php
require_once 'vendor/autoload.php';

function compareFormats(array $data, string $label): void {
    // Get comparison stats using the helper function
    $stats = toon_compare($data);

    // Generate the actual outputs
    $json = json_encode($data, JSON_PRETTY_PRINT);
    $toon = toon($data);

    // Estimate tokens for each format
    $jsonTokens = toon_estimate_tokens($data);  // This estimates TOON tokens
    $jsonActualTokens = (int) ceil(strlen($json) / 4);  // Estimate JSON tokens

    echo "=== $label ===\n";
    echo "JSON: {$stats['json']} chars (~$jsonActualTokens tokens)\n";
    echo "TOON: {$stats['toon']} chars (~" . toon_estimate_tokens($data) . " tokens)\n";
    echo "Savings: {$stats['savings']} chars ({$stats['savings_percent']})\n\n";

    echo "JSON Format:\n$json\n\n";
    echo "TOON Format:\n$toon\n\n";
    echo str_repeat('-', 60) . "\n\n";
}

// Example 1: User profile
$userProfile = [
    'id' => 12345,
    'username' => 'john_doe',
    'email' => 'john@example.com',
    'created_at' => '2024-01-15T10:30:00Z',
    'settings' => [
        'theme' => 'dark',
        'notifications' => [
            'email' => true,
            'push' => false,
            'sms' => false
        ],
        'privacy' => [
            'profile_visible' => true,
            'show_email' => false
        ]
    ]
];

compareFormats($userProfile, 'User Profile');

// Example 2: E-commerce order
$order = [
    'order_id' => 'ORD-2025-001234',
    'customer' => [
        'name' => 'Jane Smith',
        'email' => 'jane@example.com'
    ],
    'items' => [
        ['sku' => 'LAPTOP-01', 'name' => 'MacBook Pro', 'qty' => 1, 'price' => 2499.99],
        ['sku' => 'MOUSE-02', 'name' => 'Magic Mouse', 'qty' => 2, 'price' => 79.99],
        ['sku' => 'CABLE-03', 'name' => 'USB-C Cable', 'qty' => 3, 'price' => 19.99]
    ],
    'total' => 2719.93,
    'status' => 'processing'
];

compareFormats($order, 'E-commerce Order');

// Example 3: API response
$apiResponse = [
    'success' => true,
    'data' => [
        'users' => [
            ['id' => 1, 'name' => 'Alice', 'role' => 'admin', 'active' => true],
            ['id' => 2, 'name' => 'Bob', 'role' => 'user', 'active' => true],
            ['id' => 3, 'name' => 'Charlie', 'role' => 'user', 'active' => false]
        ],
        'meta' => [
            'total' => 3,
            'page' => 1,
            'per_page' => 10
        ]
    ],
    'timestamp' => '2025-01-20T15:30:00Z'
];

compareFormats($apiResponse, 'API Response');

// Summary
echo "=== SUMMARY ===\n";
echo "TOON consistently reduces token consumption by 30-60%\n";
echo "This translates directly to:\n";
echo "- Lower API costs\n";
echo "- More data in context windows\n";
echo "- Faster processing times\n";
```

Run it:

```bash
php token-comparison.php
```

## Step 6: Real-World Receipt Processing

Now let's use TOON to process receipt data that could be sent to an LLM for validation. Create `receipt-processor.php`:

```php
<?php
require_once 'vendor/autoload.php';

// Simulate OCR-extracted receipt data
$receipt = [
    'merchant' => [
        'name' => 'Corner Market',
        'address' => '123 Main St, Portland, OR',
        'phone' => '555-0123'
    ],
    'transaction' => [
        'date' => '2025-01-20',
        'time' => '14:32',
        'register' => '3',
        'transaction_id' => 'TXN-20250120-0432'
    ],
    'items' => [
        ['name' => 'Organic Bananas', 'qty' => 3, 'unit_price' => 0.79, 'total' => 2.37],
        ['name' => 'Whole Milk', 'qty' => 1, 'unit_price' => 4.29, 'total' => 4.29],
        ['name' => 'Sourdough Bread', 'qty' => 1, 'unit_price' => 5.99, 'total' => 5.99],
        ['name' => 'Free Range Eggs', 'qty' => 2, 'unit_price' => 6.49, 'total' => 12.98]
    ],
    'subtotal' => 25.63,
    'tax' => 2.05,
    'total' => 27.68,
    'payment' => [
        'method' => 'credit_card',
        'last_four' => '4532'
    ]
];

echo "=== Receipt Data Processing Example ===\n\n";
echo "This example shows how TOON can optimize receipt data for LLM processing.\n";
echo "Use cases: validation, categorization, anomaly detection.\n\n";

// Show JSON encoding
$jsonEncoded = json_encode($receipt, JSON_PRETTY_PRINT);
echo "=== JSON Format (" . strlen($jsonEncoded) . " characters) ===\n";
echo $jsonEncoded . "\n\n";

// Show TOON encoding (compact for LLM usage)
$toonEncoded = toon_compact($receipt);
echo "=== TOON Compact Format (" . strlen($toonEncoded) . " characters) ===\n";
echo $toonEncoded . "\n\n";

// Show comparison statistics
$comparison = toon_compare($receipt);
echo "=== Format Comparison ===\n";
echo "JSON size: {$comparison['json']} characters\n";
echo "TOON size: {$comparison['toon']} characters\n";
echo "Characters saved: {$comparison['savings']}\n";
echo "Reduction: {$comparison['savings_percent']}\n\n";

// Estimate token counts
$jsonTokens = (int) ceil(strlen($jsonEncoded) / 4);
$toonTokens = toon_estimate_tokens($receipt);
$tokenSavings = $jsonTokens - $toonTokens;
$tokenSavingsPercent = round(($tokenSavings / $jsonTokens) * 100, 1);

echo "=== Token Estimates ===\n";
echo "JSON: ~$jsonTokens tokens\n";
echo "TOON: ~$toonTokens tokens\n";
echo "Tokens saved: ~$tokenSavings ({$tokenSavingsPercent}%)\n\n";

// Show readable format for debugging
echo "=== TOON Readable Format (for debugging) ===\n";
echo toon_readable($receipt) . "\n\n";

// Demonstrate LLM prompt construction
echo "=== Sample LLM Prompt ===\n";
echo "Here's how you would use this data with an LLM:\n\n";

$prompt = <<<PROMPT
Analyze the following receipt data and perform these validations:
1. Verify that item totals (qty * unit_price) match the stated totals
2. Confirm the subtotal equals the sum of all item totals
3. Check that subtotal + tax equals the final total
4. Identify the purchase category (grocery, restaurant, retail, etc.)
5. Flag any anomalies or unusual patterns

Receipt data in TOON format:
$toonEncoded

Please provide:
- Validation results for each check
- Purchase category with confidence level
- Any detected anomalies or concerns
PROMPT;

echo "```\n$prompt\n```\n\n";

// Cost analysis
$costPerThousandTokens = 0.01; // Example rate (Claude Haiku)
$jsonCost = ($jsonTokens / 1000) * $costPerThousandTokens;
$toonCost = ($toonTokens / 1000) * $costPerThousandTokens;
$costSavings = $jsonCost - $toonCost;

echo "=== Cost Analysis (at $0.01 per 1K tokens) ===\n";
echo "JSON cost: $" . number_format($jsonCost, 4) . "\n";
echo "TOON cost: $" . number_format($toonCost, 4) . "\n";
echo "Savings per request: $" . number_format($costSavings, 4) . "\n";
echo "Savings for 10,000 requests: $" . number_format($costSavings * 10000, 2) . "\n";
echo "Savings for 100,000 requests: $" . number_format($costSavings * 100000, 2) . "\n\n";

// Data validation example
echo "=== Quick Validation Check ===\n";
$calculatedSubtotal = 0;
foreach ($receipt['items'] as $item) {
    $itemTotal = $item['qty'] * $item['unit_price'];
    $match = abs($itemTotal - $item['total']) < 0.01 ? 'OK' : 'MISMATCH';
    echo "{$item['name']}: {$item['qty']} × \${$item['unit_price']} = \$$itemTotal ($match)\n";
    $calculatedSubtotal += $item['total'];
}

echo "\nSubtotal check: \$$calculatedSubtotal ";
echo abs($calculatedSubtotal - $receipt['subtotal']) < 0.01 ? "(OK)\n" : "(MISMATCH)\n";

$calculatedTotal = $receipt['subtotal'] + $receipt['tax'];
echo "Total check: \${$receipt['subtotal']} + \${$receipt['tax']} = \$$calculatedTotal ";
echo abs($calculatedTotal - $receipt['total']) < 0.01 ? "(OK)\n" : "(MISMATCH)\n";
```

Run it:

```bash
php receipt-processor.php
```

## Step 7: Building a Complete Example

Let's build a complete example that processes log data. Create `log-processor.php`:

```php
<?php
require_once 'vendor/autoload.php';

use HelgeSverre\Toon\EncodeOptions;

class LogProcessor {
    private array $logs = [];
    private EncodeOptions $options;

    public function __construct() {
        $this->options = new EncodeOptions(indent: 2, delimiter: ',');
    }

    public function addLog(string $level, string $message, array $context = []): void {
        $this->logs[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'context' => $context
        ];
    }

    public function getLogs(): array {
        return $this->logs;
    }

    public function exportAsJson(): string {
        return json_encode($this->logs, JSON_PRETTY_PRINT);
    }

    public function exportAsToon(): string {
        return toon($this->logs, $this->options);
    }

    public function getSummary(): array {
        $summary = [
            'total_logs' => count($this->logs),
            'levels' => [],
            'recent' => array_slice($this->logs, -3)
        ];

        foreach ($this->logs as $log) {
            $level = $log['level'];
            $summary['levels'][$level] = ($summary['levels'][$level] ?? 0) + 1;
        }

        return $summary;
    }
}

// Simulate application logs
$processor = new LogProcessor();

// Add various log entries
$processor->addLog('INFO', 'Application started');
$processor->addLog('INFO', 'Database connected', ['host' => 'localhost', 'port' => 3306]);
$processor->addLog('WARNING', 'Cache miss', ['key' => 'user:1234', 'ttl' => 3600]);
$processor->addLog('INFO', 'User authenticated', ['user_id' => 1234, 'ip' => '192.168.1.1']);
$processor->addLog('ERROR', 'Payment failed', [
    'user_id' => 1234,
    'amount' => 99.99,
    'error_code' => 'insufficient_funds'
]);
$processor->addLog('INFO', 'Email sent', ['to' => 'user@example.com', 'template' => 'welcome']);
$processor->addLog('WARNING', 'High memory usage', ['used' => '2GB', 'limit' => '4GB']);
$processor->addLog('INFO', 'Cache updated', ['keys' => 15, 'duration_ms' => 23]);
$processor->addLog('ERROR', 'API timeout', ['endpoint' => '/api/users', 'timeout' => 30]);
$processor->addLog('INFO', 'Application shutdown gracefully');

// Compare formats
echo "=== Log Processing Example ===\n\n";

$logs = $processor->getLogs();
$summary = $processor->getSummary();

echo "Total logs collected: " . count($logs) . "\n\n";

echo "=== Summary in TOON Format ===\n";
echo toon($summary) . "\n\n";

echo "=== Full Logs Comparison ===\n";
$jsonLogs = $processor->exportAsJson();
$toonLogs = $processor->exportAsToon();

// Use the compare helper
$comparison = toon_compare($logs);
echo "JSON size: {$comparison['json']} characters\n";
echo "TOON size: {$comparison['toon']} characters\n";
echo "Size reduction: {$comparison['savings_percent']}\n\n";

// Show sample output
echo "=== TOON Format Sample (first 500 chars) ===\n";
echo substr($toonLogs, 0, 500) . "...\n\n";

// Demonstrate sending to LLM for analysis
$llmPrompt = "Analyze these application logs and identify any critical issues:\n\n" . $toonLogs;

echo "=== LLM Prompt ===\n";
echo "Prompt size: " . strlen($llmPrompt) . " characters\n";
echo "Estimated tokens: " . toon_estimate_tokens($logs) . "\n\n";

echo "With JSON, this would be " . strlen($jsonLogs) . " characters\n";
echo "TOON saves approximately " . (strlen($jsonLogs) - strlen($toonLogs)) . " characters\n";
```

Run it:

```bash
php log-processor.php
```

## Troubleshooting Common Issues

### Issue 1: Composer Not Found
**Solution**: Install Composer from https://getcomposer.org/download/

### Issue 2: PHP Version Too Old
**Solution**: Upgrade to PHP 8.1+ using your system's package manager or https://www.php.net/downloads

### Issue 3: Autoloader Not Working
**Solution**: Run `composer dump-autoload` to regenerate the autoloader

### Issue 4: Special Characters Not Encoding Properly
**Solution**: Ensure your PHP files are UTF-8 encoded and use proper string quoting

### Issue 5: Memory Issues with Large Datasets
**Solution**: Increase PHP memory limit in php.ini or use `ini_set('memory_limit', '256M')`

## Validation and Testing

Create a test file `validate-toon.php` to ensure everything works:

```php
<?php
require_once 'vendor/autoload.php';

$tests = [
    'Simple string' => 'hello',
    'Number' => 42,
    'Boolean' => true,
    'Null' => null,
    'Array' => [1, 2, 3],
    'Object' => ['key' => 'value'],
    'Nested' => [
        'level1' => [
            'level2' => [
                'level3' => 'deep'
            ]
        ]
    ]
];

$passed = 0;
$failed = 0;

foreach ($tests as $name => $value) {
    try {
        $encoded = toon($value);
        echo "OK $name: " . substr(str_replace("\n", "\\n", $encoded), 0, 50) . "\n";
        $passed++;
    } catch (Exception $e) {
        echo "FAIL $name: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\nResults: $passed passed, $failed failed\n";
```

## Next Steps

Congratulations! You've learned the fundamentals of TOON. Here's where to go next:

1. **Integrate with OpenAI PHP**: See Tutorial 2 for detailed OpenAI integration
2. **Use with Laravel**: Tutorial 3 covers Laravel and Prism integration
3. **Advanced Optimization**: Tutorial 4 explores token optimization strategies
4. **Build RAG Systems**: Tutorial 5 shows TOON in Retrieval-Augmented Generation

### Additional Resources

- [TOON GitHub Repository](https://github.com/helgesverre/toon)
- [Token Economics Guide](https://platform.openai.com/tokenizer)
- [PHP LLM Libraries](https://github.com/topics/llm-php)

### Community

- Report issues: https://github.com/helgesverre/toon/issues
- Contribute: Fork the repository and submit pull requests
- Share your use cases in the discussions

## Summary

You've learned how to:
- Install and configure TOON
- Encode various data types efficiently
- Compare token consumption with JSON
- Configure TOON for different use cases
- Process real-world data for LLM analysis

TOON typically reduces token consumption by 30-60%, which directly translates to:
- Lower API costs
- More efficient use of context windows
- Faster response times from LLMs

Start using TOON in your projects today and see immediate cost savings!