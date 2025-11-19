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

TOON PHP is a port of the TOON (Token-Oriented Object Notation) format specification, which achieves 30-60% token reduction vs JSON for LLM contexts. The implementation strictly follows the **TOON Specification v1.3+** (see `docs/SPEC.md`).

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
- **Examples**: Example code in documentation and examples/ directory must be valid, executable PHP syntax that matches the actual codebase.
- **Spec Files**: The official TOON specification lives in `docs/SPEC.md`. Always validate implementation changes against the spec.

### Development Workflow

**When adding features:**

1. **Check the spec** - Review `docs/SPEC.md` to ensure proposed change conforms to TOON specification
2. **Check spec requirements** - Review `docs/spec-requirements.md` for normative requirements (142 total requirements)
3. Write tests first in appropriate test file under `tests/`
4. Implement in relevant src file following spec requirements
5. Run `just dev` to format and test
6. Update CHANGELOG.md following Keep a Changelog format
7. Run `just pr` before submitting

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

## Specification Compliance

**CRITICAL**: This library implements the official TOON Specification v1.3+ (docs/SPEC.md). All code changes MUST conform to the specification.

### Key Specification Requirements

The specification defines 142 normative requirements across 19 sections:

- 104 MUST/REQUIRED requirements (critical)
- 12 MUST NOT prohibitions (critical)
- 17 SHOULD recommendations (high priority)
- 9 MAY/OPTIONAL features (low priority)

**Before making changes**:

1. Read relevant sections in `docs/SPEC.md`
2. Check `docs/spec-requirements.md` for specific requirements
3. Validate your implementation against encoder/decoder conformance checklists (§13 of spec)

**Encoder Conformance (Section 13.1)**: Encoders MUST produce UTF-8 output with LF line endings, use consistent indentation (no tabs), escape exactly 5 characters (\\, ", \n, \r, \t), quote delimiter-containing strings, emit accurate array lengths, preserve key order, normalize numbers to non-exponential form, convert -0 to 0, convert NaN/±Infinity to null, and emit no trailing spaces or newlines.

**Decoder Conformance (Section 13.2)**: Decoders MUST parse array headers correctly, split only on active delimiters, unescape valid escapes only, type unquoted primitives correctly, enforce strict-mode rules when enabled, accept optional # length markers, and preserve array/object order.

## Important Reminders

1. **Follow the spec** - ALL code changes must conform to TOON Specification v1.3+ in `docs/SPEC.md`
2. **Use justfile commands** - Always prefer `just` over raw composer/vendor commands
3. **Keep CHANGELOG.md updated** - Document all user-facing changes
4. **No scattered test files** - All tests go in `tests/` directory only
5. **No root-level reports** - Documentation goes in `docs/` folder
6. **Valid PHP in docs** - All example code must be syntactically correct and match actual code
7. **Verify examples** - Run `php -l` on any example files to ensure syntax validity
8. **Format before commit** - Always run `just fix` or `just dev`
9. **Test after changes** - Verify with `just test` before pushing
