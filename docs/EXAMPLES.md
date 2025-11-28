# TOON Encoding Examples

Comprehensive examples of TOON encoding for different data structures.

## Nested Objects

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

## Primitive Arrays

```php
echo Toon::encode([
    'tags' => ['reading', 'gaming', 'coding']
]);
```

Output:

```
tags[3]: reading,gaming,coding
```

## Tabular Arrays (Uniform Objects)

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

## Non-uniform Object Arrays

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

## Array of Arrays

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
// tags[3	]: a	b	c

// Pipe delimiter
$options = new EncodeOptions(delimiter: '|');
echo Toon::encode(['tags' => ['a', 'b', 'c']], $options);
// tags[3|]: a|b|c
```

### Preset Configurations

```php
use HelgeSverre\Toon\EncodeOptions;

// Maximum compactness (production)
$compact = EncodeOptions::compact();

// Human-readable (debugging)
$readable = EncodeOptions::readable();

// Tab-delimited (spreadsheets)
$tabular = EncodeOptions::tabular();
```
