# TOON (Token-Oriented Object Notation)

A PHP port of [johannschopplich/toon](https://github.com/johannschopplich/toon) - a compact data format designed to reduce token consumption when sending structured data to Large Language Models.

## What is TOON?

TOON is a compact, human-readable format for passing structured data to LLMs while reducing token consumption by 30-60% compared to standard JSON. It achieves this by:

- Removing redundant syntax (braces, brackets, unnecessary quotes)
- Using indentation-based nesting (like YAML)
- Employing tabular format for uniform data rows (like CSV)
- Including explicit array lengths and field declarations

## Installation

Install via Composer:

```bash
composer require helgesverre/toon
```

## Requirements

- PHP 8.1 or higher

## Quick Start

TOON provides convenient helper functions for common use cases:

```php
// Basic encoding
echo toon(['user' => 'Alice', 'score' => 95]);
// user: Alice
// score: 95

// Compact format (minimal indentation)
echo toon_compact($largeDataset);

// Readable format (generous indentation)
echo toon_readable($debugData);

// Compare token savings
$stats = toon_compare($myData);
echo "Savings: {$stats['savings_percent']}";
// Savings: 45.3%
```

### Preset Configurations

Choose the right format for your use case:

```php
use HelgeSverre\Toon\EncodeOptions;

// Maximum compactness (production)
$compact = EncodeOptions::compact();

// Human-readable (debugging)
$readable = EncodeOptions::readable();

// Tab-delimited (spreadsheets)
$tabular = EncodeOptions::tabular();

// With length markers
$withMarkers = EncodeOptions::withLengthMarkers();
```

**New to TOON?** Check out our [step-by-step tutorials](tutorials) to learn how to integrate TOON with OpenAI, Anthropic, Laravel, and more.

## Basic Usage

```php
use HelgeSverre\Toon\Toon;

// Simple values
echo Toon::encode('hello');        // hello
echo Toon::encode(42);             // 42
echo Toon::encode(true);           // true
echo Toon::encode(null);           // null

// Arrays
echo Toon::encode(['a', 'b', 'c']);
// [3]: a,b,c

// Objects
echo Toon::encode([
    'id' => 123,
    'name' => 'Ada',
    'active' => true
]);
// id: 123
// name: Ada
// active: true
```

## Advanced Examples

### Nested Objects

```php
echo Toon::encode([
    'user' => [
        'id' => 123,
        'email' => 'ada@example.com',
        'metadata' => [
            'active' => true,
            'score' => 9.5
        ]
    ]
]);
```

Output:

```
user:
  id: 123
  email: ada@example.com
  metadata:
    active: true
    score: 9.5
```

### Primitive Arrays

```php
echo Toon::encode([
    'tags' => ['reading', 'gaming', 'coding']
]);
```

Output:

```
tags[3]: reading,gaming,coding
```

### Tabular Arrays (Uniform Objects)

When all objects in an array have the same keys with primitive values, TOON uses an efficient tabular format:

```php
echo Toon::encode([
    'items' => [
        ['sku' => 'A1', 'qty' => 2, 'price' => 9.99],
        ['sku' => 'B2', 'qty' => 1, 'price' => 14.5]
    ]
]);
```

Output:

```
items[2]{sku,qty,price}:
  A1,2,9.99
  B2,1,14.5
```

### Non-uniform Object Arrays

When objects have different keys, TOON falls back to list format:

```php
echo Toon::encode([
    'items' => [
        ['id' => 1, 'name' => 'First'],
        ['id' => 2, 'name' => 'Second', 'extra' => true]
    ]
]);
```

Output:

```
items[2]:
  - id: 1
    name: First
  - id: 2
    name: Second
    extra: true
```

### Array of Arrays

```php
echo Toon::encode([
    'pairs' => [['a', 'b'], ['c', 'd']]
]);
```

Output:

```
pairs[2]:
  - [2]: a,b
  - [2]: c,d
```

## Configuration Options

Customize encoding behavior with `EncodeOptions`:

```php
use HelgeSverre\Toon\EncodeOptions;

// Custom indentation (default: 2)
$options = new EncodeOptions(indent: 4);
echo Toon::encode(['a' => ['b' => 'c']], $options);
// a:
//     b: c

// Tab delimiter instead of comma (default: ',')
$options = new EncodeOptions(delimiter: "\t");
echo Toon::encode(['tags' => ['a', 'b', 'c']], $options);
// tags[3\t]: a	b	c

// Pipe delimiter
$options = new EncodeOptions(delimiter: '|');
echo Toon::encode(['tags' => ['a', 'b', 'c']], $options);
// tags[3|]: a|b|c

// Length marker prefix (default: false)
$options = new EncodeOptions(lengthMarker: '#');
echo Toon::encode(['tags' => ['a', 'b', 'c']], $options);
// tags[#3]: a,b,c
```

## Special Value Handling

### String Quoting

TOON only quotes strings when necessary:

```php
echo Toon::encode('hello');           // hello (no quotes)
echo Toon::encode('true');            // "true" (quoted - looks like boolean)
echo Toon::encode('42');              // "42" (quoted - looks like number)
echo Toon::encode('a:b');             // "a:b" (quoted - contains colon)
echo Toon::encode('');                // "" (quoted - empty string)
echo Toon::encode("line1\nline2");    // "line1\nline2" (quoted - control chars)
```

### DateTime Objects

DateTime objects are automatically converted to ISO 8601 format:

```php
$date = new DateTime('2025-01-01T00:00:00+00:00');
echo Toon::encode($date);
// "2025-01-01T00:00:00+00:00"
```

### Special Numeric Values

Non-finite numbers are converted to null:

```php
echo Toon::encode(INF);     // null
echo Toon::encode(-INF);    // null
echo Toon::encode(NAN);     // null
```

## Helper Functions

TOON provides global helper functions for convenience:

```php
// Basic encoding
$toon = toon($data);

// Compact (minimal indentation)
$compact = toon_compact($data);

// Readable (generous indentation)
$readable = toon_readable($data);

// Tabular (tab-delimited)
$tabular = toon_tabular($data);

// Compare with JSON
$stats = toon_compare($data);
// Returns: ['toon' => 450, 'json' => 800, 'savings' => 350, 'savings_percent' => '43.8%']

// Get size estimate
$size = toon_size($data);

// Estimate token count (4 chars/token heuristic)
$tokens = toon_estimate_tokens($data);
```

## Real-World Examples

### OpenAI Integration

```php
use OpenAI\Client;

$client = OpenAI::client($apiKey);

// Encode large context data with TOON
$userData = [...]; // Your data
$context = toon_compact($userData);

$response = $client->chat()->create([
    'model' => 'gpt-4o-mini',
    'messages' => [
        ['role' => 'system', 'content' => 'Data is in TOON format.'],
        ['role' => 'user', 'content' => $context],
    ],
]);
```

### Anthropic/Claude Integration

```php
use Anthropic\Anthropic;
use Anthropic\Resources\Messages\MessageParam;

$client = Anthropic::factory()->withApiKey($apiKey)->make();

$largeDataset = [...]; // Your data
$toonContext = toon_compact($largeDataset);

$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-20250514',
    'max_tokens' => 1000,
    'messages' => [
        MessageParam::with(role: 'user', content: $toonContext),
    ],
]);
```

See the [`examples/`](examples) directory for complete working examples.

## Tutorials

Comprehensive step-by-step guides for learning TOON and integrating it with popular PHP AI/LLM libraries:

### Getting Started
- **[Getting Started with TOON](tutorials/01-getting-started.md)** (10-15 min)
  Learn the basics: installation, encoding, configuration, and your first LLM integration.

### Framework Integrations
- **[OpenAI PHP Client Integration](tutorials/02-openai-integration.md)** (15-20 min)
  Integrate TOON with OpenAI's official PHP client. Covers messages, function calling, and streaming.

- **[Laravel + Prism AI Application](tutorials/03-laravel-prism-integration.md)** (20-30 min)
  Build a complete Laravel AI chatbot using TOON and Prism for multi-provider support.

### Advanced Topics
- **[Token Optimization Strategies](tutorials/04-token-optimization-strategies.md)** (20-25 min)
  Deep dive into token economics, RAG optimization, and cost reduction strategies.

- **[Building a RAG System with Neuron AI](tutorials/05-rag-system-neuron-ai.md)** (30-40 min)
  Create a production-ready RAG pipeline with TOON, Neuron AI, and vector stores.

See the [`tutorials/`](tutorials) directory for all tutorials and learning paths.

## Token Savings

TOON achieves significant token savings compared to JSON and XML:

| Dataset                        | JSON Tokens | XML Tokens | TOON Tokens | vs JSON | vs XML |
| ------------------------------ | ----------- | ---------- | ----------- | ------- | ------ |
| GitHub Repositories (100)      | 6,276       | 8,673      | 3,346       | 46.7%   | 61.4%  |
| Analytics Data (180 days)      | 4,550       | 7,822      | 1,458       | 68.0%   | 81.4%  |
| E-Commerce Orders (50)         | 4,136       | 6,381      | 2,913       | 29.6%   | 54.3%  |
| Employee Records (100)         | 3,350       | 4,933      | 1,450       | 56.7%   | 70.6%  |

**Average savings: 50.2% vs JSON, 66.9% vs XML**

## Format Rules

### Objects

- Key-value pairs with colons
- Indentation-based nesting (2 spaces by default)
- Empty objects shown as `key:`

### Arrays

- **Primitives**: Inline format with length `tags[3]: a,b,c`
- **Uniform objects**: Tabular format with headers `items[2]{sku,qty}: A1,2`
- **Mixed/non-uniform**: List format with hyphens

### Indentation

- 2 spaces per level (configurable)
- No trailing spaces
- No final newline

## PHP-Specific Limitations

### Numeric Key Handling

PHP automatically converts numeric string keys to integers in arrays:

```php
// PHP automatically converts numeric keys
$data = ['123' => 'value'];  // Key becomes integer 123
echo Toon::encode($data);    // "123": value (quoted as string)
```

The library handles this by quoting numeric keys when encoding.

## Testing

Run the test suite:

```bash
composer test
```

Run with code coverage:

```bash
composer test:coverage       # Generates HTML report in coverage/
composer test:coverage-text  # Shows coverage in terminal
```

Run static analysis:

```bash
composer analyse
```

## Benchmarks

The `benchmarks/` directory contains tools for measuring TOON's token efficiency compared to JSON and XML across realistic datasets.

### Running Benchmarks

```bash
cd benchmarks
composer install
composer run benchmark
```

The benchmark tests four dataset types:

- **GitHub Repositories** (100 records) - Repository metadata
- **Analytics Data** (180 days) - Time-series metrics
- **E-Commerce Orders** (50 orders) - Nested order structures
- **Employee Records** (100 records) - Tabular data

Results are saved to `benchmarks/results/token-efficiency.md` with detailed comparisons and visualizations.

### Token Counting

For accurate token counts, set your Anthropic API key:

```bash
cd benchmarks
cp .env.example .env
# Add your ANTHROPIC_API_KEY to .env
```

Without an API key, the benchmark uses character/word-based estimation.

See [benchmarks/README.md](benchmarks/README.md) for detailed documentation.

## Use Cases

TOON is ideal for:

- Sending structured data in LLM prompts
- Reducing token costs in API calls to language models
- Improving context window utilization
- Making data more human-readable in AI conversations

**Note**: TOON is optimized for LLM contexts and is not intended as a replacement for JSON in APIs or data storage.

## Differences from JSON

TOON is not a strict superset or subset of JSON. Key differences:

- No decode function (one-way transformation)
- Optimized for readability and token efficiency, not for parsing
- Uses whitespace-significant formatting
- Includes metadata like array lengths and field headers

## Credits

- Original TypeScript implementation: [johannschopplich/toon](https://github.com/johannschopplich/toon)
- PHP port: [HelgeSverre](https://github.com/HelgeSverre)

## License

MIT License - see LICENSE file for details
