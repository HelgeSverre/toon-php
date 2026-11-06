# Conformance and mutation testing follow-up

Local testing on October 23, 2026, against the uncommitted multi-version integration on `refactor/multi-version`. No production source was changed during this follow-up. The changes strengthen public-behavior tests and fixture comparisons. Nothing was committed or pushed.

## Conformance

- The PHPUnit versioned fixture suite passes for the 3.3 compatibility profile, pinned 4.1, and pinned 4.4.
- An independent runner, using `php -n` without Composer, passes all 538 official 4.1 fixtures and all 564 official 4.4 fixtures. It also checks every successful encoding in both strict and non-strict decoding: 358 round trips for 4.1 and 320 for 4.4.
- Numeric fixture assertions now compare integer values exactly and float-domain values using exact binary64 equality, instead of PHPUnit's approximate numeric comparator. This retains the documented host-domain projection; it does not claim arbitrary-precision decimal support.
- Successful official encodings now round-trip in both strict and non-strict mode in the regular PHPUnit suite.
- Upstream fixture expectations and the released 3.3 snapshots were not changed.

## Mutation runs

Infection 0.33.2, default mutators, eight workers, 15-second mutant timeout, PCOV, and `--only-covering-test-cases`. The first run covers the entire source tree. The follow-up runs cover the 4.4 implementation and its codec wrapper. No mutators were disabled or survivors ignored to improve the score.

| Completed run | Generated | Killed by tests | Fatal/error | Timeout | Escaped | Raw MSI |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| Entire source tree, before follow-up tests | 4,371 | 3,832 | 28 | 34 | 477 | 89.09% |
| 4.4 subset of that initial run | 1,141 | 1,054 | 7 | 8 | 72 | 93.69% |
| 4.4 after first test additions | 1,142 | 1,069 | 8 | 8 | 57 | 95.01% |
| 4.4 final confirmation | 1,142 | 1,072 | 7 | 9 | 54 | 95.27% |

MSI includes fatal/error and timed-out mutants as detected; it is not the percentage killed by ordinary test assertions alone. The final nine timeouts alter loop progress or termination in delimiter scanning, field scanning, object/list/table parsing, blank-line scanning, and escape decoding. These were reviewed separately from normal test failures. One escape-loop mutant alternated between fatal/error and timeout across reruns; the combined detected count is unchanged.

These runs mutate covered statements by default. Infection's reported 100% mutation-code coverage is therefore not a claim of 100% source line or branch coverage. The extra generated mutant in the later 4.4 runs comes from exercising a previously untested positive-zero formatting path.

The initial full-tree baseline also has 229 escaped mutants in 4.1 and 176 in legacy/shared code. Those 405 survivors were not exhaustively classified or eliminated in this follow-up. The entire tree was not rerun after the test additions, so 89.09% is the initial full-tree baseline, not a newly measured final whole-package score.

## Gaps closed through public API tests

- Reject a deeper second list item and deeper second table row, even when the first record established a valid scope.
- Reject a space-only unquoted table field name in both modes.
- Accept keyed rows without a space after the colon, preserving their complete first cell.
- Reject the final low surrogate U+DFFF, in addition to the earlier surrogate boundary cases.
- Preserve canonical positive zero, integer-valued floats, negative integer-valued floats, and ordinary fractional decimals under application precision overrides.
- Preserve the smallest negative subnormal float. This detects three otherwise surviving changes that would normalize a tiny negative nonzero value to zero when its reciprocal overflows.
- Keep number-prefixed text such as `1st` and `1eagle` unquoted.
- Report source line numbers correctly after comments and blank lines.

The new cases all pass against the existing implementation. This round demonstrated test gaps, not a production defect requiring a runtime change.

## Review of the 54 remaining 4.4 survivors

The classifications below apply to the supported public encode/decode contract with normalized inputs. They do not claim equivalent behavior for arbitrary calls to internal implementation classes or identical diagnostic wording.

| Group | Count | Why the mutations survive |
| --- | ---: | --- |
| Internal defaults, visibility, and unused key-context paths | 10 | Public codec calls supply the line/context arguments explicitly; internal helper visibility is not a supported API. |
| Diagnostic wording on already malformed headers | 5 | Both paths still throw a syntax exception with the same line/snippet; only the explanatory message changes. |
| Redundant trimming | 7 | Other normalization/token-parsing steps preserve the same public token result. The float digit trim also has an equivalent decimal-point representation without trimming. |
| Redundant casts | 3 | A located bracket is already an integer; four hexadecimal digits fit a PHP integer; arithmetic converts a numeric exponent string to the same integer. |
| Unreachable boundary differences | 5 | A colon and delimiter/bracket cannot occupy the same character position; zero/exponent boundaries are excluded by prior branches; padding length is already non-negative. |
| Four-character Unicode validation regex changes | 3 | The input is an at-most-four-character substring, so the removed anchors/flag do not broaden the accepted four-hex-digit language. |
| Equivalent parser/encoder paths and return removal | 11 | Empty values fall through to the same result, blank metadata is skipped, alternate primitive-array paths emit the same header, and field-shape checks enforce matching schema/value kinds. |
| Negative-zero intermediate normalization | 6 | The supported encoder canonicalizes either intermediate representation to `0`; the tiny negative nonzero cases are now explicitly protected. |
| Finite-float formatting preconditions | 4 | PHP JSON already emits lowercase exponents, extra array padding is unused, and normalized finite values do not throw in JSON conversion. |

The raw score remains 95.27%; equivalent or diagnostic-only survivors were not removed from its denominator. No adjusted 100% score is claimed.

## Final checks

The full suite passes on PHP 8.1.34, 8.2.35, 8.3.36, 8.4.27, and 8.5.12: **4,826 tests and 86,711 assertions on each runtime**. Pint, PHPStan level 9, and `git diff --check` also pass.

Reproduce the main checks from the repository:

```sh
php vendor/bin/phpunit --no-coverage --do-not-cache-result --no-progress
php -n tests/Support/check-runtime.php
php -d memory_limit=1G -d pcov.enabled=1 vendor/bin/infection \
  --threads=8 --only-covering-test-cases --no-progress \
  --initial-tests-php-options='-d memory_limit=1G -d pcov.enabled=1'
```

For the focused run, add `--filter=src/Codec/V44`. The recorded runs used a temporary configuration with the same default mutators and explicit log paths, preserving the repository's existing Infection logs. The full JSON reports and independent runner scripts were stored in the original chat's temporary `work/` directory and are not included in this repository. These scores are historical results; rerunning the commands creates new evidence and may produce different timing classifications. The official fixture and round-trip checks remain reproducible through `tests/VersionedFixturesTest.php`.
