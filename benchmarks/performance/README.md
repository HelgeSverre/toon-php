# TOON-PHP Performance Benchmarks

This directory contains comprehensive performance benchmarks for the TOON-PHP library using [PHPBench](https://phpbench.readthedocs.io/).

## What's Measured

The benchmark suite measures four key performance dimensions:

### 1. **Execution Time** (EncodeBench, DecodeBench)

- How fast encoding/decoding operations complete
- Measured across different data sizes (small, medium, large, xlarge)
- Tests different TOON format types (inline, tabular, list, nested arrays)

### 2. **Memory Usage** (All benchmarks)

- Peak memory consumption during operations
- Important for large datasets and serverless environments
- Tracked automatically by PHPBench

### 3. **Throughput** (ThroughputBench)

- Operations per second for sustained workloads
- Tests realistic API response structures
- Includes round-trip (encode + decode) performance

### 4. **Scalability** (ScalabilityBench)

- How performance scales with data size
- Tests from 10 to 100K items
- Helps identify O(n) complexity characteristics

## Running Benchmarks

### Quick Start

```bash
# Run all performance benchmarks with default report
just benchmark-performance

# Run with summary report (cleaner output)
just benchmark-perf-summary

# Run both token efficiency + performance benchmarks
just benchmark-all
```

### Baseline Comparison

To detect performance regressions, establish a baseline:

```bash
# Store current performance as baseline
just benchmark-baseline

# Later, compare against baseline
just benchmark-compare
```

### Direct PHPBench Usage

```bash
# Run specific benchmark file
./vendor/bin/phpbench run benchmarks/performance/EncodeBench.php

# Run with JSON output
./vendor/bin/phpbench run --report=default --output=json

# Run specific benchmark method
./vendor/bin/phpbench run --filter=benchEncodeSmall

# Store results for later comparison
./vendor/bin/phpbench run --store --tag=my-experiment
```

## Understanding Results

### Report Columns

- **benchmark**: The benchmark class name
- **subject**: The specific test method
- **set**: Parameter set (for parameterized benchmarks)
- **revs**: Number of revolutions (iterations per measurement)
- **its**: Number of iterations (measurements taken)
- **mem_peak**: Peak memory usage in bytes
- **best**: Fastest time measured
- **mean**: Average time across all iterations
- **mode**: Most common time value
- **worst**: Slowest time measured
- **stdev**: Standard deviation (consistency)
- **rstdev**: Relative standard deviation (percentage)
- **diff**: Difference compared to baseline

### Interpreting Results

**Time Units:**

- Times are typically shown in microseconds (μs) or milliseconds (ms)
- Lower is better

**Memory:**

- Shown in bytes (B), kilobytes (KB), or megabytes (MB)
- Lower is better

**Standard Deviation:**

- Lower rstdev means more consistent performance
- High rstdev may indicate GC pauses or other variability

**Diff (Baseline Comparison):**

- `+10%` means 10% slower than baseline (regression)
- `-10%` means 10% faster than baseline (improvement)

## Benchmark Files

### EncodeBench.php

Tests encoding performance across different scenarios:

- **Size-based**: Small (10), Medium (100), Large (1K), XLarge (10K) items
- **Format-based**: Inline, Tabular, List, Nested arrays
- **Special cases**: Primitives, Deeply nested structures

### DecodeBench.php

Tests decoding performance:

- Same size variations as encoding
- Tests parsing of all TOON format types
- Measures string parsing overhead

### ThroughputBench.php

Tests sustained performance:

- Typical API responses (20-100 items)
- Small payloads (single objects)
- Large payloads (100+ items)
- Round-trip operations (encode + decode)

### ScalabilityBench.php

Tests performance at scale:

- Data sizes: 10, 50, 100, 500, 1K, 5K, 10K, 50K, 100K items
- Uses parameterized benchmarks for easy comparison
- Helps identify performance curves

## CI Integration

Pull requests run nine representative workloads on PHP 8.3: small/medium objects,
tables and nested arrays for both encoding and decoding, plus a round trip.
The base revision's benchmark definitions and PHPBench installation run against
both codec autoloaders on the same runner. Each workload uses 500 revolutions,
50 warmup revolutions and seven iterations; the gate compares median time per
revolution. Samples are not repeatedly discarded until a target variance is met.

A slowdown **greater than 15%** fails the comparison. Missing workloads, errors,
malformed XML, invalid timing values or fewer than three samples also fail;
missing results never count as a passing benchmark. A noisy run should be
investigated and rerun, not suppressed by changing the threshold.
The PR gate also requires exactly nine workloads, so a partially matching filter
cannot silently reduce coverage on both sides.

The Markdown report is written to the Actions job summary, and the raw XML and
report are uploaded as the `benchmark-comparison` artifact even on regression.
Same-repository PRs get one updated bot comment. Fork and Dependabot PRs retain
the summary/artifact without a write token. The separate commenting job has PR
write permission; jobs executing benchmark code have only content-read access.

Run the full PHP 8.1–8.5 matrix manually through **Actions → Performance
Benchmarks → Run workflow**. It also runs on version tags. Full runs retain
PHPBench's per-subject iteration/revolution settings and upload XML measurements.
No tag or release is needed to request a full run.

To compare existing result files locally:

```sh
php tests/Support/compare-benchmarks.php base.xml candidate.xml comparison.md
```

Exit status is 0 for a passing comparison, 1 for a regression, and 2 for invalid
or incomplete input. An optional fourth argument requires an exact workload
count. The parser uses DOM, already required by PHPUnit in the
development environment; the library's runtime requirements are unchanged.

## Adding New Benchmarks

To add a new benchmark:

1. Create a new PHP file in `benchmarks/performance/`
2. Use the namespace `Benchmarks\Performance`
3. Add PHPBench annotations:

```php
<?php

declare(strict_types=1);

namespace Benchmarks\Performance;

use HelgeSverre\Toon\Toon;

class MyNewBench
{
    /**
     * @Revs(1000)
     * @Iterations(10)
     */
    public function benchMyFeature(): void
    {
        // Your benchmark code here
        Toon::encode(['test' => 'data']);
    }
}
```

### PHPBench Annotations

- `@Revs(n)`: Number of times to execute the code per iteration
- `@Iterations(n)`: Number of times to measure (for statistical accuracy)
- `@ParamProviders("methodName")`: Use method to provide test parameters
- `@BeforeMethods("methodName")`: Run setup method before benchmark

## Best Practices

1. **Consistent Test Data**: Use fixed seeds or consistent data generation
2. **Warmup**: PHPBench handles warmup automatically
3. **Isolation**: Each benchmark runs in isolation (process isolation)
4. **Iterations**: Use enough iterations for statistical significance (usually 10)
5. **Revolutions**: Adjust based on operation speed (fast ops need more revs)

## Performance Goals

Based on typical use cases:

- **Small payloads** (<100 items): Sub-millisecond encoding
- **Medium payloads** (100-1K items): 1-10ms encoding
- **Large payloads** (10K+ items): Linear scaling (O(n))
- **Memory**: Should scale linearly with data size
- **Throughput**: >1000 ops/sec for typical API responses

## Troubleshooting

### Benchmarks running too slow

- Reduce `@Revs` count for slower operations
- Reduce test data size in constructor

### Inconsistent results (high rstdev)

- Increase `@Iterations` count
- Close other applications
- Check for background processes

### Out of memory

- Reduce test data size
- Increase PHP memory limit in `phpbench.json`

## References

- [PHPBench Documentation](https://phpbench.readthedocs.io/)
- [TOON Specification](../../docs/SPEC.md)
- [Token Efficiency Benchmarks](../README.md)
