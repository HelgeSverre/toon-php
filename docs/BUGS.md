# Spec Deviations — Verification and Resolution

This document records the deviations from `docs/SPEC.md` (TOON v3.3) that were
reported, adversarially verified against the spec and codebase, and resolved.

**Status baseline (after fixes):** `vendor/bin/phpunit` — 752+ tests, all passing;
PHPStan level 9 clean; Laravel Pint clean. Regression suite:
`tests/Spec/BugFixesTest.php`.

---

## Fixed spec-compliance issues

| ID | Spec | Fix | Status |
|----|------|-----|--------|
| TOON-001 | §5 | Empty (and whitespace-only) documents decode to `[]` (empty object) in both strict and lenient modes, instead of throwing / returning `null`. | ✅ Fixed |
| TOON-004 | §9.2 | A list item that is an inner array header on the hyphen line (`- [M]: …`) decodes to the inner array. | ✅ Fixed |
| TOON-005 | §9.4 | A list item that is an object with its first field on the hyphen line (`- key: …`) decodes to the object. | ✅ Fixed |
| TOON-006 | §10 | A bare `-` list item decodes to an empty object (`[]` in PHP), not `null`. | ✅ Fixed |
| TOON-007 | §10, §12 | The (previously unreachable) empty-object list-item encoder path emits a bare `-` with no trailing space. | ✅ Fixed |
| TOON-008 | §8 | A bare `key:` with no children decodes to an empty object (`[]`), not `null`. | ✅ Fixed |
| TOON-009 | §7.4 | A quoted key prefix in an array header (`"my-key"[3]:`) is unescaped. | ✅ Fixed |
| TOON-010 | §9.1, §11.2 | Empty inline-array and tabular tokens decode to the empty string, instead of throwing. | ✅ Fixed |
| TOON-011 | §7.1 | Colon detection tracks quote state forward, so a key ending in an escaped backslash (`"a\\": c`) parses. | ✅ Fixed |
| TOON-012 | §7.1, §11.2 | An unquoted backslash is a literal character; escapes apply only inside quoted strings. | ✅ Fixed |
| TOON-014 | §12 | `EncodeOptions`/`DecodeOptions` reject `indent < 1`; `EncodeOptions::compact()` uses `indent: 1` so nested output round-trips. | ✅ Fixed |

**Additional fix found while hardening:** a nested array-of-objects (or array-of-arrays)
appearing as a list item did not round-trip — the decoder passed the wrong expected
depth to `parseArray`, so the inner items (at hyphen+1 per §9.4) were never captured.
Fixed in `Parser::parseListArrayFromHeader`.

The `Toon::validate()` path (`src/Decoder/Validator.php`) was fixed in lockstep with
the decoder so validation and decoding agree on all of the above.

---

## Not bugs — deliberate, spec-conformant choices

These were reported as deviations but are permitted by the spec.

- **Root `encode([]) === ''`** (§9.1/§5). The root empty-array rule is a `SHOULD`, and
  §5 states an empty document decodes to an empty object. In PHP `[]` is indistinguishable
  from an empty object, so emitting an empty document is a conformant treatment of the
  ambiguous root value. The decoder still accepts the canonical `[]` token.
- **`encode(['tags' => []]) === 'tags[0]:'`** (§9.1). The legacy header form is explicitly
  permitted: *"Encoders MAY emit the legacy header form `key[0<delim?>]:`."* The decoder
  accepts both `tags: []` and `tags[0]:`.
- **No `keyFolding` / `flattenDepth` / `expandPaths` options** (§13.4). These are
  *"optional transformations … [that] default to `off`."* Not implementing an optional
  feature whose default is `off` is conformant.

---

## Test-suite fixes

- **TEST-001** — `tests/ArraysTest.php` class renamed from `pArraysTest` to `ArraysTest`.
- Tests that codified the pre-fix behavior (indent `0`, empty→`null`/throw, the
  negative-indent message, legacy-form expectations) were updated to the spec-correct
  behavior.

The remaining test-quality observations from the original report (weak
`assertStringContainsString` assertions, misleading test names for the tab-delimiter and
empty-object cases, stale `#`-length-marker regexes) are lower-priority hardening items;
new coverage lives in `tests/Spec/BugFixesTest.php` and is being extended via mutation
testing (Infection) and round-trip fuzzing.
