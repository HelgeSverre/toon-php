# CLAUDE.md

This file provides guidance to Claude Code when working in this repository. Read `AGENTS.md` for the shared commands and compatibility rules, and `docs/multi-version.md` for the detailed versioning contract. Keep this guidance consistent with those files.

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

# Run CI checks (lint + analyse + test, no formatting)
just ci

# Run tests only
just test

# Run tests with coverage
just coverage

# Run benchmarks
just benchmark
```

## Architecture

The package supports multiple TOON wire-format versions in one release. The Composer package version does not select the syntax. Existing calls use the **3.3 compatibility profile**, preserving release 3.2.1 behavior; **4.1 and 4.4 are explicitly opt-in**. Symfony AI Mate is a downstream consumer of the default API.

### Public API and Version Selection

```php
use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\SpecVersion;
use HelgeSverre\Toon\Toon;

$data = ['message' => '#hello'];
$legacy = Toon::encode($data); // Existing 3.3 behavior.

$encode41 = new EncodeOptions(version: SpecVersion::V4_1);
$decode41 = new DecodeOptions(version: SpecVersion::V4_1);
$wire = Toon::encode($data, $encode41);
$decoded = Toon::decode($wire, $decode41);
$valid = Toon::validate($wire, $decode41);

$readable41 = EncodeOptions::readable()->withVersion(SpecVersion::V4_1);
$lenient41 = $decode41->withStrict(false);
```

- Select the version per call. Never auto-detect syntax, retry with another grammar, or use mutable global configuration.
- `strict` is independent of `version`: leniency adjusts validation within the selected grammar.
- Preserve existing named and positional constructor arguments. Copies made with `withIndent()`, `withDelimiter()`, or `withStrict()` retain the selected version.
- Existing presets and convenience helpers default to 3.3. Helpers accepting options can select 4.1 or 4.4; `toon_readable()` itself remains a legacy helper.

### Core Components

1. **Toon** (`src/Toon.php`) exposes `encode()`, `decode()`, and `validate()`. It dispatches to an internal codec once per encode/decode call.
2. **Codec interface** (`src/Codec/Codec.php`) defines the encoding/decoding boundary.
3. **3.3 codec** (`src/Codec/V33.php`) wraps the original root-level normalizer/encoder/primitives and `src/Decoder/`. Preserve their class names and behavior for direct consumers; these legacy helpers do not dispatch by version.
4. **4.1 codec** (`src/Codec/V41.php`) owns its implementation under `src/Codec/V41/`, including `Fields.php` for nested and keyed tables. These classes are internal.
5. **4.4 codec** (`src/Codec/V44.php`) owns its implementation under `src/Codec/V44/`, adapted from PR #9; its non-strict recoveries are isolated from older versions.
6. **Shared infrastructure** includes options, constants, exceptions, and `src/LineWriter.php`. Share only behavior that is identical across versions.

Keep grammar-specific fixes inside the corresponding codec. Avoid scattering version checks across the original parser and encoder.

### Format and PHP Representation

The selected grammar determines format selection. The 4.1 encoder uses mandatory tabular/nested-field or keyed-tabular forms where eligible, inline primitive arrays, and list forms where required. It also handles comment-aware quoting and the 4.1 empty-array forms. Consult the selected spec instead of treating these as interchangeable formatting preferences.

All decoders retain the PHP associative-array API. Empty objects and arrays both decode to `[]`; sequential numeric-key objects can become PHP lists. Do not silently change return types to make JSON shape comparisons pass. The opt-in encoders use `stdClass` internally to distinguish objects from lists, including empty objects.

For field maps, preserve arbitrary string keys, including NUL-prefixed keys; read normalized object entries through `get_object_vars()` instead of dynamic property access. Compare key sets using string semantics, preserving first-row field order. Float formatting must preserve round-trip precision without fixed-place re-rounding.

## Development Guidelines

### Code Standards

- **PHP Version**: 8.1+ (uses readonly properties, enum support)
- **Strict Types**: All files must use `declare(strict_types=1);`
- **Tooling**: [Mago](https://mago.carthage.software) 1.57.0, installed via Composer (`carthage-software/mago`) and configured in `mago.toml`
- **Code Style**: Mago formatter (tuned to the existing Laravel-style layout) + linter — run `just fix` before commits; `just lint` checks
- **Static Analysis**: Mago analyzer (`just analyse`) — no baseline; all code must pass. Prefer precise docblock types (`positive-int`, `list<...>`, `@phpstan-assert-if-true`) over `assert()` calls
- **Type Safety**: Express invariants with docblock types (`positive-int`, `non-negative-int`, `list<...>`, `@phpstan-assert-if-true`) rather than `assert()` calls

### File Organization

- **Tests**: All tests MUST go in `tests/` directory. Never create test PHP files elsewhere in the repo.
- **Documentation**: Reports and documentation go in `docs/`. Maintain root agent instruction files in place; do not create root-level reports.
- **Examples**: Example code in documentation and examples/ directory must be valid, executable PHP syntax that matches the actual codebase.
- **Spec Files**: Pin specifications under `docs/specs/{version}/SPEC.md` and official fixtures under `tests/Fixtures/spec/{version}/`, retaining licenses and revision metadata. `docs/SPEC.md` remains the legacy document.
- **Vendored Files**: Preserve upstream fixtures and Mate source verbatim. `mago.toml` excludes `tests/Fixtures/` from formatting and linting while retaining Mate symbols for analysis.

### Development Workflow

**When adding features:**

1. **Select the version** - Identify the affected codec and read its pinned `docs/specs/{version}/SPEC.md`
2. **Check compatibility** - Read `docs/multi-version.md` and the legacy deviations; do not change default behavior as a side effect of opt-in support
3. Write tests first in appropriate test file under `tests/`
4. Implement in relevant src file following spec requirements
5. Run `just dev` to format and test
6. Update CHANGELOG.md following Keep a Changelog format
7. Run `just pr` before submitting

**When debugging:**

1. Confirm the options select the intended version
2. Trace normalization and format detection in that codec
3. Check its primitive encoding and decoder paths
4. For readable 4.1 output, use `EncodeOptions::readable()->withVersion(SpecVersion::V4_1)`
5. Run PHP code directly for quick checks (don't create scattered test files)

**When running tests:**

```bash
# Use just commands (preferred)
just test
just coverage

# Run specific test file
vendor/bin/phpunit tests/PrimitivesTest.php

# Run specific test method
vendor/bin/phpunit --filter test_encode_safe_strings
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

User-facing documentation should focus on **what the package does**. Engineering records under `docs/` may document fixture provenance, compatibility comparisons, coverage, and verification evidence.

**In user-facing documentation, avoid:**

- Development process details
- Self-referential language ("we verified", "our testing")
- Meta-commentary about development history
- References to comparing against other implementations
- Details about how features were validated

**In user-facing documentation, include:**

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
php -r "require 'vendor/autoload.php'; echo \HelgeSverre\Toon\Toon::encode(['test' => 'data']);"

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

Changes must respect the selected version's pinned specification and the existing public compatibility contract.

- **3.3**: preserve released behavior. The known differences from upstream fixtures are documented in `docs/legacy-fixture-deviations.md`; this profile is not a claim of perfect conformance. Do not silently correct these differences during opt-in codec work.
- **4.1**: use `docs/specs/4.1/SPEC.md` and its complete pinned fixture suite. Keep 4.1-specific fixes isolated.
- **4.4**: use `docs/specs/4.4/SPEC.md` and its complete pinned fixture suite. Preserve the exactly five non-strict recoveries and the documented numeric policy.
- **No floating spec updates**: the existing `just sync-spec` recipe fetches upstream main into legacy docs. Do not use it for routine multi-version work. Update versioned specs and fixtures deliberately from an exact revision.

### Fixture and Compatibility Checks

- `tests/VersionedFixturesTest.php` runs official 4.1 and 4.4 expectations and compares legacy cases against the released baseline, including omitted-version calls. Encoding fixtures preserve JSON objects; decoding comparisons use the documented PHP representation.
- `tests/Fixtures/legacy-3.2.1.json` must remain a release baseline. `tests/Support/capture-legacy.php` verifies pinned source hashes; never regenerate it from the branch under test.
- Keep version-isolation, option-copy, PHP-specific value/field, canonical-output, round-trip, and Mate compatibility tests. Use PHP-native cases where JSON fixtures cannot express host values.
- Run the complete suite after codec changes, plus `just lint` and `just analyse`. PHP 8.1–8.5 are covered by CI. Check both opt-in runtime paths with `php -n tests/Support/check-runtime.php` to avoid hidden dependencies on development polyfills.
- For coverage, use `just coverage` or the driver-specific command in `docs/coverage-review.md`. Add tests for meaningful public behavior, not reflection tests merely to cover private constructors.

### Adding Another Spec Version

1. Pin its specification and fixtures, with licenses and provenance.
2. Add a `SpecVersion` enum case, an internal codec, and the dispatch arm in `Toon`.
3. Extend fixture and version-isolation coverage, retaining all legacy snapshots.
4. Document syntax and representation differences. Do not change the default just because another version is available.

Syntax versions coexist in one package. The integration branch can become main; separate long-lived branches are for maintaining package releases, not for selecting syntax at runtime.

## Important Reminders

1. **Respect version boundaries** - Follow the selected pinned spec while preserving the default 3.3 compatibility profile
2. **Use justfile commands** - Always prefer `just` over raw composer/vendor commands
3. **Keep CHANGELOG.md updated** - Document all user-facing changes
4. **No scattered test files** - All tests go in `tests/` directory only
5. **No root-level reports** - Documentation goes in `docs/` folder
6. **Valid PHP in docs** - All example code must be syntactically correct and match actual code
7. **Verify examples** - Run `php -l` on any example files to ensure syntax validity
8. **Format before commit** - Always run `just fix` or `just dev`
9. **Test after changes** - Verify with `just test` before pushing
