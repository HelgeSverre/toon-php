# TOON Benchmarks

This directory contains benchmarking tools for evaluating the token efficiency of the TOON (Token-Oriented Object Notation) format compared to JSON and XML.

## Overview

The benchmarks measure how effectively TOON reduces token consumption when encoding structured data, which is crucial for minimizing costs and latency when working with Large Language Models (LLMs).

## Installation

```bash
cd benchmarks
composer install
```

## Running the Benchmarks

### Token Efficiency Benchmark

This benchmark compares TOON, JSON, and XML token counts across multiple realistic datasets:

```bash
composer run benchmark
```

Or directly:

```bash
php scripts/token-efficiency.php
```

### Token Counting Methods

The benchmark supports two token counting methods:

1. **Anthropic API** (Recommended) - Uses Claude's actual tokenizer for accurate counts
2. **Estimation** (Fallback) - Character/word-based estimation when API is unavailable

To use the Anthropic API method:

1. Copy `.env.example` to `.env`:

   ```bash
   cp .env.example .env
   ```

2. Add your Anthropic API key to `.env`:
   ```
   ANTHROPIC_API_KEY=your_api_key_here
   ```

## Benchmark Results

Results are saved to `results/token-efficiency.md` and include:

- Token counts for each format (TOON, JSON, XML)
- Percentage savings for TOON compared to other formats
- Visual progress bars showing relative token usage
- Summary statistics across all benchmarks

## Datasets

The benchmark evaluates four different types of structured data:

1. **GitHub Repositories** (100 records) - Repository metadata with stars, forks, etc.
2. **Analytics Data** (180 days) - Time-series web metrics with views, clicks, conversions
3. **E-Commerce Orders** (50 orders) - Nested order data with customers and items
4. **Employee Records** (100 records) - Tabular employee data

All datasets are generated using [Faker PHP](https://github.com/FakerPHP/Faker) with seeded randomization for reproducibility.

## Architecture

```
benchmarks/
├── src/
│   ├── Datasets.php       # Dataset generators
│   ├── Formatters.php     # TOON, JSON, XML formatters
│   ├── TokenCounter.php   # Token counting utilities
│   └── Report.php         # Markdown report generator
├── scripts/
│   └── token-efficiency.php  # Main benchmark script
├── data/                  # Generated test data (if needed)
└── results/              # Benchmark output reports
```

## Extending the Benchmarks

### Adding New Datasets

Add new dataset generators to `src/Datasets.php`:

```php
public function generateCustomData(): array
{
    // Your dataset generation logic
    return $data;
}
```

Then add the dataset to the benchmarks array in `scripts/token-efficiency.php`:

```php
$benchmarks[] = [
    'name' => 'Custom Dataset',
    'description' => 'Description of what this tests',
    'data' => fn() => $datasets->generateCustomData(),
];
```

### Adding New Formats

Implement formatters in `src/Formatters.php`:

```php
public static function toCustomFormat(array $data): string
{
    // Your formatting logic
}
```

## Example Output

```
╔════════════════════════════════════════════════════════════════╗
║       TOON Token Efficiency Benchmark (PHP)                   ║
╚════════════════════════════════════════════════════════════════╝

Token Counting Method: Anthropic API

[1/4] Running: GitHub Repositories...
  → Formatting data... ✓
  → Counting tokens... ✓
  → Results:
      TOON: 3,346 tokens
      JSON: 6,276 tokens
      XML:  8,673 tokens
  → TOON saves 46.7% vs JSON, 61.4% vs XML

...

╔════════════════════════════════════════════════════════════════╗
║  Benchmark Complete! 🎉                                        ║
╚════════════════════════════════════════════════════════════════╝
```

## Requirements

- PHP 8.1 or higher
- Composer
- (Optional) Anthropic API key for accurate token counting

## License

MIT - Same as the parent TOON library
