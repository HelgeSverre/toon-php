# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

TOON (Token-Oriented Object Notation) is a PHP 8.1+ library that converts PHP data structures into a compact format optimized for LLM contexts. It reduces token consumption by 30-60% compared to JSON through:
- Removing redundant syntax (braces, brackets, unnecessary quotes)
- Using indentation-based nesting
- Employing tabular format for uniform data rows
- Including explicit array lengths and field declarations

This is a standalone library with no runtime dependencies (only dev dependencies for testing/analysis).

## Quick Start Commands

**Always prefer `just` commands over raw composer/vendor commands when available:**

```bash
# Show all available commands
just

# Quick development cycle (format + test)
just dev

# Prepare for PR (format + analyse + test)
just pr

# Run CI checks (analyse + test, no formatting)
just ci

# Run tests only
just test

# Run tests with coverage
just test-coverage

# Watch mode for testing
just watch-test

# Run benchmarks
just benchmark
```

## Architecture

### Core Components

1. **Toon** (`src/Toon.php`) - Main entry point with single static `encode()` method
2. **Normalize** (`src/Normalize.php`) - Converts PHP values to JSON-compatible structures
3. **Encoders** (`src/Encoders.php`) - Core encoding logic with format detection
4. **Primitives** (`src/Primitives.php`) - Encodes primitive values with smart quoting
5. **LineWriter** (`src/LineWriter.php`) - Manages output lines and indentation
6. **EncodeOptions** (`src/EncodeOptions.php`) - Configuration with presets
7. **Constants** (`src/Constants.php`) - Shared syntax tokens
8. **Helpers** (`src/helpers.php`) - Global helper functions

### Format Selection Logic

The encoder automatically selects the optimal format:
1. **Inline format** for arrays of primitives: `[3]: a,b,c`
2. **Array-of-arrays format** for nested arrays with list items
3. **Tabular format** for uniform object arrays: `[2]{id,name}: 1,Alice`
4. **List format** (default) for mixed structures with hyphen markers

### Key Implementation Details

**PHP Array Behavior**: PHP converts numeric string keys to integers. The library handles this by quoting numeric keys when encoding.

**String Quoting Rules**: Strings are quoted only when necessary:
- Reserved words: "true", "false", "null"
- Numeric strings: "42", "3.14"
- Strings with special characters
- Empty strings: ""

**Enum Normalization**:
- `BackedEnum`: Extracts backing value
- `UnitEnum`: Uses case name as string

## Development Guidelines

### Code Standards

- **PHP Version**: 8.1+ (uses readonly properties, enum support)
- **Strict Types**: All files must use `declare(strict_types=1);`
- **PHPStan Level**: 9 (maximum strictness)
- **Code Style**: Laravel Pint (run `just fix` before commits)
- **Type Safety**: Heavy use of `assert()` after type checks

### File Organization

- **Tests**: All tests MUST go in `tests/` directory. Never create test PHP files elsewhere in the repo.
- **Documentation**: All documentation MUST go in `docs/` folder if needed. Never create report/summary markdown files in the root.
- **Examples**: Example code in documentation must be valid, executable PHP syntax.

### Development Workflow

**When adding features:**
1. Write tests first in appropriate test file under `tests/`
2. Implement in relevant src file
3. Run `just dev` to format and test
4. Update CHANGELOG.md following Keep a Changelog format
5. Run `just pr` before submitting

**When debugging:**
1. Check normalization step (`Normalize.php`)
2. Trace through `Encoders.php` format detection
3. Check primitive encoding in `Primitives.php`
4. Use `toon_readable()` helper for debugging output
5. Run PHP code directly for quick checks (don't create scattered test files)

**When running tests:**
```bash
# Use just commands (preferred)
just test
just test-coverage

# Run specific test file
vendor/bin/phpunit tests/PrimitivesTest.php

# Run specific test method
vendor/bin/phpunit --filter testEncodesStringsWithoutQuotes
```

## Release Guidelines

### Version Control

- **CHANGELOG.md**: MUST be updated for all user-facing changes. Follow Keep a Changelog format with sections: Added, Changed, Deprecated, Removed, Fixed, Security.

### Release Titles

Version numbers only, with no descriptive text:
- ✅ Correct: `v1.1.0`, `v1.0.1`, `v2.0.0`
- ❌ Incorrect: `v1.1.0 - New Features`, `Version 1.0.0: Initial Release`

### Release Notes

Must be factual and straightforward:
- **No emojis** - Plain text only
- **No marketing language** - Avoid "exciting", "amazing", "revolutionary"
- **Technical focus** - What changed, not how great it is
- **Simple headings** - Use "Added", "Changed", "Fixed", not "What's New"

**Good example:**
```
## Changes in v1.1.0

### Added
- Justfile for task automation
- Token efficiency benchmarks
- PHPDoc documentation

### Changed
- Benchmark output formatting simplified
```

## Documentation Guidelines

### Content Focus

Documentation must focus on **what the package does**, not how it was built.

**Always avoid:**
- Development process details
- Self-referential language ("we verified", "our testing")
- Meta-commentary about development history
- References to comparing against other implementations
- Details about how features were validated

**Always include:**
- Package features and capabilities
- User-facing functionality
- Clear, direct descriptions of behavior
- Valid, executable PHP code examples

### Example Quality

**Good (feature-focused):**
```markdown
## Features
- Reduces token consumption by 30-60% compared to JSON
- Supports nested objects and arrays
- Configurable delimiters and formatting options
```

**Bad (process-focused):**
```markdown
## Implementation
This implementation has been verified against the TypeScript version
with extensive testing to ensure correctness.
```

## Common Tasks

### Running Benchmarks
```bash
# Preferred: use just command
just benchmark

# Alternative: direct composer
cd benchmarks && composer benchmark
```
Results are saved to `benchmarks/results/token-efficiency.md`

### Quick PHP Testing
When testing quick PHP snippets, run them directly:
```bash
# Direct execution (preferred for quick tests)
php -r "require 'vendor/autoload.php'; echo Toon\Toon::encode(['test' => 'data']);"

# Never create temporary test files throughout the repo
```

### Quality Checks
```bash
# Before any commit
just dev

# Before creating PR
just pr

# For CI validation
just ci
```

## Important Reminders

1. **Use justfile commands** - Always prefer `just` over raw composer/vendor commands
2. **Keep CHANGELOG.md updated** - Document all user-facing changes
3. **No scattered test files** - All tests go in `tests/` directory only
4. **No root-level reports** - Documentation goes in `docs/` folder
5. **Valid PHP in docs** - All example code must be syntactically correct
6. **Format before commit** - Always run `just fix` or `just dev`
7. **Test after changes** - Verify with `just test` before pushing
