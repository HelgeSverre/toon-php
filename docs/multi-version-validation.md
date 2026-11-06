# Local multi-version integration validation

For the later conformance rerun and mutation results, including additional test cases, see [conformance and mutation testing](conformance-mutation-review.md).

Validated October 23, 2026, on `refactor/multi-version`, with local changes based on `231f6cbee919516ac3a7a7981c7689d3568624b5`. This records the local integration; no release version has been assigned and nothing was pushed.

## Scope

- Preserve release 3.2.1 behavior as the default 3.3 compatibility profile, including direct legacy helper classes and constructor arguments.
- Retain the existing isolated 4.1 codec and add explicit `SpecVersion::V4_4` dispatch to an isolated 4.4 codec.
- Adapt PR #9 at `7fe28817d320c99115b5122eb69d5152f6ca225a`, including its accumulated nesting and empty-value fixes from #8. Retain PHP object identity, extension-free Unicode handling, and the package's associative-array decoding contract.
- Keep Pint/PHPStan. Do not introduce #7's independent tooling migration or #8's optional folding/expansion into the frozen legacy profile.

## Results

| Runtime | Full suite | Extension-free 4.1 and 4.4 smoke checks |
| --- | --- | --- |
| PHP 8.1.34 | 4,813 tests, 83,001 assertions; pass | Pass |
| PHP 8.2.35 | 4,813 tests, 83,001 assertions; pass | Pass |
| PHP 8.3.36 | 4,813 tests, 83,001 assertions; pass | Pass |
| PHP 8.4.27 | 4,813 tests, 83,001 assertions; pass | Pass |
| PHP 8.5.12 | 4,813 tests, 83,001 assertions; pass | Pass |

Also passed:

- PHPStan level 9 (`vendor/bin/phpstan analyse --no-progress --memory-limit=1G`).
- Pint across all source and tests, using PHP 8.3 with the existing pinned formatter (`vendor/bin/pint src tests --test`).
- `composer validate --strict` and `git diff --check`.
- A complete suite with line coverage on PHP 8.5.10 / PCOV 1.0.12: 679 of 690 executable lines in the new 4.4 codec covered (98.41%); the parser covers 168 of 168. This is line coverage, not branch/path coverage or proof of every input combination.

The 4.4 specification, changelog, versioning document, licenses and all fixture files were compared byte-for-byte with upstream tag `v4.4.0`, commit `d09c3d33e37389345a74bd96981eb96dd9be6c5e`. All 564 official fixtures are exercised: 160 encode and 404 decode. Encoding checks exact wire text; decoding checks values and key order. Every successful official encoding is decoded again using the documented PHP projection; table field reordering follows the header.

## Additional behavior exercised

- Existing release snapshots, omitted-version defaults, Symfony Mate's vendored response encoder, named/positional options, immutable copies, helpers and interleaved version selection.
- All five 4.4 non-strict recoveries, with malformed lengths, invalid bytes/escapes, unexpected indentation, trailing text and row-width errors rejected in both modes.
- Empty, numeric-key and NUL-prefixed objects and fields; newline-containing keys; BOM strings; Unicode escape boundaries; serialization hooks; non-finite values; resource normalization without consuming the stream.
- 1,800 deterministic generated document round trips across comma, pipe and tab delimiters and indentation sizes 1, 2 and 4, plus 300 generated finite binary64 values. Seeds are kept in `tests/Version44RoundTripTest.php`.
- Float thresholds, subnormal and maximum values under application `serialize_precision` settings 3, 17 and -1; the setting is restored after encoding. Numeric overflow to infinity remains an exact input string in 4.4; underflow may produce zero, as documented.

## Compatibility limits and review

The legacy runtime implementation, its options, the 4.1 implementation and `tests/Fixtures/legacy-3.2.1.json` are unchanged. Only the version enum and public dispatch gain the new codec. No runtime dependency or optional extension was added. The smoke check runs with `php -n`, bypassing Composer's development polyfills.

Objects still decode as associative arrays, so empty object/list and sequential numeric-key distinctions can be lost after decoding. The 3.3 profile intentionally retains its documented deviations. Inputs must be finite, acyclic structures and serialization hooks must terminate. These existing limits are not claims of lossless JSON AST support.

The final self-review checked dispatch, grammar isolation, normalization, object identity, canonical formatting, scopes, quote/delimiter handling, error paths and provenance. No blocking findings remain in this integration. Tests and line coverage provide evidence for these behaviors; they do not establish exhaustive correctness for arbitrary inputs.

## Reproduce

Run the following with each PHP version from 8.1 through 8.5:

```sh
php vendor/bin/phpunit --no-coverage --do-not-cache-result --no-progress
php -n tests/Support/check-runtime.php
```

Then run the static and formatting checks listed above. The normal CI matrix also covers all five versions and both extension-free smoke checks.
