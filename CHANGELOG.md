# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
