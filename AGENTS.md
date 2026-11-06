# AGENTS.md

Quick reference for AI coding agents working on TOON PHP. See `docs/multi-version.md` for the architecture and compatibility contract.

## Commands

- **Run all tests**: `just test` or `vendor/bin/phpunit`
- **Run versioned fixtures**: `vendor/bin/phpunit tests/VersionedFixturesTest.php`
- **Run single test file**: `vendor/bin/phpunit tests/PrimitivesTest.php`
- **Run single test method**: `vendor/bin/phpunit --filter test_encode_safe_strings`
- **Test with coverage**: `just coverage` (requires a coverage driver; see `docs/coverage-review.md`)
- **Check opt-in codecs without optional extensions**: `php -n tests/Support/check-runtime.php`
- **Format code**: `just fix` (Mago formatter)
- **Lint**: `just lint` (Mago format check + linter)
- **Static analysis**: `just analyse` (Mago analyzer, no baseline)
- **Dev cycle**: `just dev` (format + test)
- **Pre-PR**: `just pr` (format + analyse + test)

## Multiple Spec Versions

- The Composer package version is independent of the TOON wire-format version.
- Existing calls default to `SpecVersion::V3_3`, preserving release 3.2.1 behavior. Consumers include Symfony AI Mate; do not silently change their output or decoded PHP types.
- TOON 4.1 and 4.4 are opt-in through `new EncodeOptions(version: SpecVersion::V4_1)` and `new DecodeOptions(version: SpecVersion::V4_1)`, or `withVersion()`; use `SpecVersion::V4_4` for 4.4.
- `strict` controls validation within the selected version. Never infer versions from input, fall back between grammars, or introduce a global version setting.
- Preserve existing constructor argument names, positions, defaults, and option-copy behavior. Presets and helpers without explicit versioned options retain 3.3 behavior.
- All decoders return associative arrays for objects. Empty object/list and sequential numeric-key distinctions can be lost on decoding; this is a documented compatibility constraint. The opt-in encoders preserve object identity internally with `stdClass`.

## Architecture and Specs

- **Main entry**: `src/Toon.php` — static `encode()`, `decode()`, and `validate()`; version dispatch happens once per encode/decode call.
- **Codec boundary**: internal `src/Codec/Codec.php`, implemented by `Codec\V33`, `Codec\V41`, and `Codec\V44`.
- **3.3**: `Codec\V33` wraps the original `src/Normalize.php`, `src/Encoders.php`, `src/Primitives.php`, and `src/Decoder/`. Keep those class names and their legacy semantics compatible; direct helper-class calls do not dispatch by version.
- **4.1**: grammar-specific code lives under `src/Codec/V41/`, including normalization, encoding, field handling, and decoding. Keep version-specific changes here rather than adding version conditionals throughout legacy code.
- **4.4**: grammar-specific code lives under `src/Codec/V44/`, adapted from PR #9. Preserve the older codecs; do not apply 4.4 non-strict rules to 4.1. Keep object identity through normalization and require no optional extensions.
- **Shared infrastructure**: options, exceptions, constants, and `LineWriter`; share behavior only when it is identical between versions.
- **Specifications**: use `docs/specs/{3.3,4.1,4.4}/SPEC.md` for the selected version. `docs/SPEC.md` remains the legacy document.
- **Legacy deviations**: `docs/legacy-fixture-deviations.md` records known 3.3 differences. Do not silently fix them during a 4.1 change or claim complete 3.3 conformance.
- **Spec updates**: pin exact upstream revisions. The current `just sync-spec` fetches upstream main into legacy docs; do not use it for routine multi-version work.
- **New versions**: add an enum case, a codec and dispatch arm, pinned spec/fixtures with provenance, version-isolation tests, and documentation. Adding support must not change the default. Syntax versions coexist in the package; long-lived branches are for package release maintenance.

## Verification and Code Style

- Keep all pinned official fixtures under `tests/Fixtures/spec/{version}/`, with licenses and revision metadata. Do not alter upstream expectations to make tests pass.
- Preserve `tests/Fixtures/legacy-3.2.1.json`. Its generator verifies the pinned release source; never regenerate snapshots from the implementation under test.
- Cover canonical encoding, decoded values and order, strict/lenient behavior, option copying, version isolation, and PHP-specific edge cases. Run the full suite, including Mate compatibility tests, after codec changes.
- Coverage improvements should test meaningful public behavior, not private constructors or unreachable internals solely to raise percentages.
- **PHP 8.1+**, `declare(strict_types=1);`, namespace `HelgeSverre\Toon`, Mago analyzer clean with no baseline. Prefer precise docblock types (`positive-int`, `list<...>`, `@phpstan-assert-if-true`) over `assert()`. No new runtime packages or extension requirements.
- **Tests** go in `tests/` only; **docs/reports** go in `docs/` only. Root agent instruction files are maintained in place.
- Vendored fixtures and Mate source are preserved verbatim and excluded from formatting and linting through `mago.toml`. Keep Mate source available to the analyzer for symbol resolution.
