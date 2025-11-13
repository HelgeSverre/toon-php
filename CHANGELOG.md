# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] - 2025-11-13

### Breaking Changes

This major release aligns with **TOON Specification v2.0**, which removes the optional `#` length marker prefix from array headers. The library version now matches the spec version for clarity.

#### Removed Features

- **`EncodeOptions::$lengthMarker` parameter** - The optional length marker parameter has been removed from the constructor
- **`EncodeOptions::withLengthMarkers()` preset** - This preset method has been removed
- **`EncodeOptions::withLengthMarker()` method** - This fluent setter has been removed

#### Changed Behavior

- **Encoder**: Always emits `[N]` format (e.g., `[3]: a,b,c`). The deprecated `[#N]` format is no longer supported.
- **Decoder**: Now rejects `[#N]` format with `SyntaxException`. Previously accepted both `[N]` and `[#N]` formats.

#### Migration Guide

**Before (v1.x):**
```php
use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\Toon;

// Using preset with length markers
$options = EncodeOptions::withLengthMarkers();
$toon = Toon::encode($data, $options);
// Output: [#3]: a,b,c

// Using constructor
$options = new EncodeOptions(lengthMarker: '#');
$toon = Toon::encode($data, $options);
```

**After (v2.0):**
```php
use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\Toon;

// Use default options (no length marker parameter)
$options = EncodeOptions::default();
$toon = Toon::encode($data, $options);
// Output: [3]: a,b,c

// Constructor no longer accepts lengthMarker
$options = new EncodeOptions();
$toon = Toon::encode($data, $options);
```

### Changed

- **TOON Specification updated to v2.0** - Spec now explicitly prohibits `[#N]` format
- **Encoder implementation** - Removed all length marker logic, always emits `[N]` format
- **Decoder implementation** - Added strict validation to reject `[#N]` format with clear error messages
- **EncodeOptions simplified** - Removed `lengthMarker` parameter and related methods

### Removed

- `EncodeOptions::$lengthMarker` property
- `EncodeOptions::withLengthMarkers()` static method
- `EncodeOptions::withLengthMarker()` instance method

## [1.4.0] - 2025-11-06

### Added

- **Full TOON decoder implementation**: Complete decoding functionality with strict mode support
  - Parses all TOON formats: inline arrays, list arrays, tabular arrays, nested objects
  - Strict mode validation (enabled by default) for spec compliance
  - Configurable indentation and delimiter support
  - Comprehensive error handling with specific exception types
  - Round-trip encode/decode verified working perfectly
- **Specification compliance documentation**: Created comprehensive SPEC-COMPLIANCE.md
  - Encoder conformance: 10/10 requirements verified
  - Decoder conformance: 7/7 requirements verified
  - Strict mode: 11/11 requirements verified
  - Full TOON Specification v1.3 compliance verified

### Changed

- **Architecture refactoring**: Converted Encoder from static methods to instance-based pattern
  - Encoder now stores EncodeOptions and LineWriter as readonly properties
  - Eliminated parameter threading through 9 methods (~30+ parameter passes)
  - Matches Decoder's instance-based architecture for consistency
  - Performance impact is negligible (< 3% worst case, often better)
- **Decoder improvements**: Completed Parser instance-based refactoring
  - Fixed remaining DecodeOptions parameter bugs
  - Parser now fully instance-based with stored configuration
  - All 539 tests pass, PHPStan Level 9 clean

### Added

- **Specification compliance documentation**: Created comprehensive SPEC-COMPLIANCE.md
  - Encoder conformance: 10/10 requirements verified
  - Decoder conformance: 7/7 requirements verified
  - Strict mode: 11/11 requirements verified
  - Full TOON Specification v1.3 compliance
- **Performance benchmarking**: Comprehensive PHPBench suite for performance analysis
  - EncodeBench: Measures encoding performance across data sizes (small, medium, large, xlarge) and format types (inline, tabular, list, nested)
  - DecodeBench: Measures decoding/parsing performance with same variations
  - ThroughputBench: Measures sustained operations per second for realistic workloads
  - ScalabilityBench: Measures performance scaling from 10 to 100K items
  - All benchmarks track execution time and memory usage
- **GitHub Actions workflow**: Automated performance benchmarking on every PR
  - Runs benchmarks on PHP 8.1, 8.2, 8.3, 8.4
  - Compares PR performance against main branch baseline
  - Comments results directly on pull requests
  - Detects performance regressions (>15% slower)
  - Stores benchmark results as artifacts
- **Justfile commands**: New benchmark commands for local development
  - `just benchmark-performance` - Run PHPBench with default report
  - `just benchmark-perf-summary` - Run with summary report
  - `just benchmark-all` - Run both token efficiency and performance benchmarks
  - `just benchmark-baseline` - Store current performance as baseline
  - `just benchmark-compare` - Compare against stored baseline
- **Documentation**: Comprehensive README for performance benchmarks explaining metrics, usage, and interpretation
- **Baseline benchmarks**: Saved performance baselines before and after encoder refactoring for comparison

## [1.3.0] - 2025-11-03

### Added

- **Enum support**: Native PHP enum normalization for both `BackedEnum` and `UnitEnum` types
  - BackedEnum values are extracted and normalized (e.g., `Status::ACTIVE` → `"active"`)
  - UnitEnum names are extracted and normalized (e.g., `Counting::TWO` → `"TWO"`)
  - Arrays of enum cases are properly encoded (e.g., `HttpCode::cases()` → `"[2]: 201,400"`)
  - Thanks to @AmolKumarGupta for the contribution!

## [1.2.0] - 2025-10-28

### Fixed

- **Empty array encoding**: Empty arrays now correctly output with `[0]` length marker (e.g., `items[0]:`)

### Changed

- **README**: Updated token savings table with benchmark data
- **README**: Removed outdated empty array limitations section

## [1.1.0] - 2025-10-28

### Added

- **Justfile**: Added comprehensive task automation with `just` commands for:
  - Setup and installation
  - Running tests (with coverage and watch mode)
  - Static analysis with PHPStan
  - Code formatting with Pint
  - Benchmarks (new!)
  - Quality checks and CI workflows
- **Benchmarks**: Complete token efficiency benchmark suite comparing TOON vs JSON vs XML
  - Four realistic datasets: GitHub repos, analytics data, e-commerce orders, employee records
  - Support for Anthropic API token counting or estimation fallback
  - Clean, minimal output formatting
  - Markdown report generation
- **Documentation**: Added comprehensive PHPDoc for `Toon::encode()` method
- **Tests**: Added 3 new test files with extended edge cases and normalization tests

### Changed

- **Benchmark output**: Replaced decorative box characters with clean, minimal formatting
- **README**: Enhanced with more examples and usage instructions

## [1.0.1] - 2025-10-28

### Fixed

- **Tab delimiter encoding**: Fixed tab delimiter to use actual tab character instead of literal `\t` string in array headers
- **Keyword matching**: Changed keyword detection to be case-sensitive (per TOON spec) - only `true`, `false`, `null` are quoted, not `True`, `False`, etc.
- **Key encoding**: Implemented separate `encodeKey()` method that uses identifier pattern matching (`^[A-Za-z_][\w.]*$`) instead of applying value quoting rules to keys
- **Array-of-arrays classification**: Fixed to properly validate that inner arrays contain only primitives, preventing type errors
- **Float formatting**: Improved locale-independent float formatting using `json_encode()` and `number_format()` to avoid locale-dependent decimal separators
- **Float precision**: Enhanced float formatting to preserve precision and avoid scientific notation across all platforms

### Changed

- **Object normalization**: Reordered normalization priority to check `JsonSerializable` first, then `toArray()`, then public properties only (via `get_object_vars()`) to prevent leaking private/protected properties
- **String quoting**: Added hex (`0xFF`) and binary (`0b1010`) pattern detection to ensure they are properly quoted

### Removed

- **Dead code**: Removed unreachable empty array branch in `encodeArray()` method
- **Locale manipulation**: Removed global `setlocale()` calls that could cause thread-safety issues

## [1.0.0] - 2025-10-27

### Added

- Initial stable release of TOON PHP implementation
- Core encoding functionality via `Toon::encode()` static method
- Support for primitive types (strings, numbers, booleans, null)
- Support for objects (associative arrays) with key-value pairs
- Support for arrays with multiple format options:
  - Primitive arrays: inline comma-separated format
  - Tabular arrays: efficient tabular format for uniform objects
  - List format: for non-uniform or nested structures
- Special handling for DateTime objects (ISO 8601 format)
- Intelligent string quoting (only when necessary)
- Nested data structure support with indentation-based nesting
- Configuration options via `EncodeOptions`:
  - Custom indentation (default: 2 spaces)
  - Custom delimiters (comma, tab, pipe)
  - Length marker prefix option
- Comprehensive test suite with 133 tests covering:
  - Primitive values
  - Objects and nested objects
  - Arrays (primitive, tabular, and nested)
  - Edge cases and special values
  - Custom delimiters and formatting options
  - Format invariants
- Complete documentation with usage examples
- PSR-4 autoloading
- PHP 8.1+ support with strict types

### Features

- **Token Efficiency**: Achieves 30-60% token reduction compared to JSON
- **Human Readable**: Clean, indentation-based format similar to YAML
- **Smart Formatting**: Automatically chooses optimal format for different data structures
- **Type Preservation**: Properly handles PHP types including DateTime objects
- **Safe String Handling**: Intelligent quoting for special characters and ambiguous values
- **Flexible Configuration**: Customizable indentation, delimiters, and length markers
- **PHP-Specific Optimizations**: Handles PHP array semantics and type system

### Technical Details

- Pure PHP implementation with no external dependencies
- Immutable `EncodeOptions` with fluent API
- Static final class to prevent instantiation and inheritance
- Full PHPStan static analysis compliance
- Comprehensive PHPUnit test coverage
- Follows PHP-FIG coding standards (via Laravel Pint)

[1.3.0]: https://github.com/HelgeSverre/toon-php/releases/tag/v1.3.0
[1.2.0]: https://github.com/HelgeSverre/toon-php/releases/tag/v1.2.0
[1.1.0]: https://github.com/HelgeSverre/toon-php/releases/tag/v1.1.0
[1.0.1]: https://github.com/HelgeSverre/toon-php/releases/tag/v1.0.1
[1.0.0]: https://github.com/HelgeSverre/toon-php/releases/tag/v1.0.0
