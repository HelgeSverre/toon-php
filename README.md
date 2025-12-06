# TOON (Token-Oriented Object Notation)

[![Packagist Version](https://img.shields.io/packagist/v/helgesverre/toon)](https://packagist.org/packages/helgesverre/toon)
![Total Downloads](https://img.shields.io/packagist/dt/helgesverre/toon)
[![License](https://img.shields.io/packagist/l/helgesverre/toon)](https://suno.com/song/ecb121f2-9db7-4f6a-880e-77a2aee7253f)
[![Try it](https://img.shields.io/badge/try_it-ArrayAlchemy-4E45E2)](https://arrayalchemy.com/?format=toon-php)

A PHP port of [toon-format/toon](https://github.com/toon-format/toon) - a compact data format designed to reduce token
consumption when sending structured data to Large Language Models.

## Contents

- [Quick Start](#quick-start) · [Basic Usage](#basic-usage) · [Decoding](#decoding-toon) · [Configuration](#configuration-options)
- [Tutorials](#tutorials) · [Version Compatibility](#version-compatibility) · [Development](#development)

## What is TOON?

TOON is a compact, human-readable format for structured data optimized for LLM contexts. For format details and efficiency analysis, see the [TOON Specification](https://github.com/toon-format/spec).

## Installation

Install via Composer:

```bash
composer require helgesverre/toon
```

## Requirements

- PHP 8.1 or higher

## Quick Start

```php
use HelgeSverre\Toon\Toon;

// Encode data
echo Toon::encode(['user' => 'Alice', 'score' => 95]);
// user: Alice
// score: 95

// Decode back to PHP
$data = Toon::decode("user: Alice\nscore: 95");
// ['user' => 'Alice', 'score' => 95]
```

Try it online at [ArrayAlchemy](https://arrayalchemy.com/?format=toon-php).

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

## Decoding TOON

TOON supports bidirectional conversion - you can decode TOON strings back to PHP arrays:

```php
use HelgeSverre\Toon\Toon;

// Decode simple values
$result = Toon::decode('42');           // 42
$result = Toon::decode('hello');        // "hello"
$result = Toon::decode('true');         // true

// Decode arrays
$result = Toon::decode('[3]: a,b,c');
// ['a', 'b', 'c']

// Decode objects (returned as associative arrays)
$toon = <<<TOON
id: 123
name: Ada
active: true
TOON;

$result = Toon::decode($toon);
// ['id' => 123, 'name' => 'Ada', 'active' => true]

// Decode nested structures
$toon = <<<TOON
user:
  id: 123
  email: ada@example.com
  metadata:
    active: true
    score: 9.5
TOON;

$result = Toon::decode($toon);
// ['user' => ['id' => 123, 'email' => 'ada@example.com', 'metadata' => ['active' => true, 'score' => 9.5]]]
```

**Note**: TOON objects are decoded as PHP associative arrays, not objects.

## Tabular Format

TOON's most efficient format is for uniform object arrays:

```php
echo Toon::encode([
    'users' => [
        ['id' => 1, 'name' => 'Alice', 'role' => 'admin'],
        ['id' => 2, 'name' => 'Bob', 'role' => 'user'],
    ]
]);
```

Output:

```
users[2]{id,name,role}:
  1,Alice,admin
  2,Bob,user
```

Field names are declared once in the header, then each row contains only values. This is where TOON achieves the largest token savings compared to JSON.

See [docs/EXAMPLES.md](docs/EXAMPLES.md) for more encoding examples.

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

### PHP Enums

PHP enums are automatically normalized - BackedEnum values are extracted, UnitEnum names are used:

```php
enum Status: string {
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

enum Priority: int {
    case LOW = 1;
    case HIGH = 10;
}

enum Color {
    case RED;
    case GREEN;
    case BLUE;
}

// BackedEnum with string value
echo Toon::encode(Status::ACTIVE);
// active

// BackedEnum with int value
echo Toon::encode(Priority::HIGH);
// 10

// UnitEnum (no backing value)
echo Toon::encode(Color::BLUE);
// BLUE

// Array of enum cases
echo Toon::encode(Priority::cases());
// [2]: 1,10
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

// Decoding
$data = toon_decode($toonString);

// Lenient decoding (forgiving parsing)
$data = toon_decode_lenient($toonString);

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

## Tutorials

Step-by-step guides for integrating TOON with LLM providers:

### Getting Started

- **[Getting Started with TOON](tutorials/01-getting-started.md)** (10-15 min)
  Learn the basics: installation, encoding, configuration, and your first LLM integration.

### Framework Integrations

- **[OpenAI PHP Client Integration](tutorials/02-openai-integration.md)** (15-20 min)
  Integrate TOON with OpenAI's official PHP client. Covers messages, function calling, and streaming.

- **[Laravel + Prism AI Application](tutorials/03-laravel-prism-integration.md)** (20-30 min)
  Build a complete Laravel AI chatbot using TOON and Prism for multi-provider support.

- **[Anthropic/Claude Integration](tutorials/06-anthropic-integration.md)** (20-25 min)
  Leverage Claude's 200K context window with TOON optimization. Process large datasets efficiently.

### Advanced Topics

- **[Token Optimization Strategies](tutorials/04-token-optimization-strategies.md)** (20-25 min)
  Deep dive into token economics, RAG optimization, and cost reduction strategies.

- **[Building a RAG System with TOON and Ollama](tutorials/05-rag-system-ollama.md)** (30-40 min)
  Create a production-ready RAG pipeline with TOON, Ollama embeddings, and vector similarity search.

See the [`tutorials/`](tutorials) directory for all tutorials and learning paths.

## Version Compatibility

This library tracks the [TOON Specification](https://github.com/toon-format/spec). Major versions align with spec versions.

| Library | Spec | Key Changes |
|---------|------|-------------|
| v3.0.0 | v3.0 | List-item objects with tabular first field use depth +2 for rows |
| v2.0.0 | v2.0 | Removed `[#N]` length marker; decoder rejects legacy format |
| v1.4.0 | v1.3 | Full decoder, strict mode |
| v1.3.0 | v1.3 | PHP enum support |
| v1.2.0 | v1.3 | Empty array fix |
| v1.1.0 | v1.3 | Benchmarks, justfile |
| v1.0.0 | v1.3 | Initial release |

For format details and token efficiency analysis, see the [TOON Specification](https://github.com/toon-format/spec).

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

## Use Cases

TOON is ideal for:

- Sending structured data in LLM prompts
- Reducing token costs in API calls to language models
- Improving context window utilization
- Making data more human-readable in AI conversations

**Note**: TOON is optimized for LLM contexts and is not intended as a replacement for JSON in APIs or data storage.

## Differences from JSON

TOON is not a strict superset or subset of JSON. Key differences:

- Bidirectional encoding and decoding (objects decode as associative arrays)
- Optimized for readability and token efficiency in LLM contexts
- Uses whitespace-significant formatting (indentation-based nesting)
- Includes metadata like array lengths and field headers for better LLM comprehension

## Credits

- Original TypeScript implementation: [toon-format/toon](https://github.com/toon-format/toon)
- Specification: [toon-format/spec](https://github.com/toon-format/spec)
- PHP port: [HelgeSverre](https://github.com/HelgeSverre)

## License

[MIT License](LICENSE)

## Development

### Testing

```bash
composer test                # Run tests
composer test:coverage       # Generate coverage report
composer analyse             # Static analysis
```

### Specification Sync

Keep the library aligned with upstream spec changes:

```bash
just sync-spec    # Download latest SPEC.md from upstream
just diff-spec    # Show diff after download
just autofix      # Sync spec and launch Claude Code for compliance review
```

The `autofix` command downloads the latest specification, then launches Claude Code in plan mode with the `/spec-review` prompt to analyze changes and propose implementation updates.

### Benchmarks

```bash
cd benchmarks && composer install && composer run benchmark
```

See [benchmarks/README.md](benchmarks/README.md) for details.
