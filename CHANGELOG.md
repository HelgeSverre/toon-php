# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[1.0.0]: https://github.com/HelgeSverre/toon-php/releases/tag/v1.0.0
