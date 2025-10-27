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

## Token Savings

TOON achieves significant token savings compared to JSON:

| Data Type                   | JSON Tokens | TOON Tokens | Savings |
| --------------------------- | ----------- | ----------- | ------- |
| Simple objects              | ~100        | ~58         | 42%     |
| Product catalogs            | ~500        | ~210        | 58%     |
| Large datasets (50 records) | ~1000       | ~350        | 65%     |

**Average savings: ~61%**

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

Due to PHP's type system, there are a few differences from the original JavaScript implementation:

### Empty Array/Object Ambiguity

In JavaScript, `{}` (empty object) and `[]` (empty array) are distinct types. In PHP, both are represented as `[]` when creating data structures directly in code. As a result, **all empty arrays are treated as empty objects** in this library:

```php
// These all produce the same output
echo Toon::encode([]);                    // '' (empty string)
echo Toon::encode(['user' => []]);        // 'user:' (not 'user[0]:')
echo Toon::encode(['items' => []]);       // 'items:' (not 'items[0]:')
```

**In the original TypeScript library:**

- `{items: []}` → `'items[0]:'` (empty array)
- `{user: {}}` → `'user:'` (empty object)

**In this PHP port:**

- `['items' => []]` → `'items:'` (treated as empty object)
- `['user' => []]` → `'user:'` (treated as empty object)

This is a fundamental limitation of PHP's type system and does not affect the encoding of non-empty arrays or objects. In practice, empty arrays are rare in LLM contexts, so this limitation has minimal impact.

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
