# Coverage review: multi-version codecs

Measured on `refactor/multi-version`, including the two field-handling fixes, using PHP 8.5.5, PCOV 1.0.12, and PHPUnit 10.5.58. These are **line coverage** measurements, not branch/path coverage. An initial Xdebug path-coverage attempt exceeded its default 128 MiB memory limit; the completed measurements use PCOV.

| Area | Before | After |
| --- | ---: | ---: |
| Entire source tree | 93.51% (2059/2202) | 95.38% (2106/2208) |
| 4.1 primitive encoder | 75.51% | 91.35% |
| 4.1 parser | 91.15% | 97.05% |
| 4.1 field handling | 94.67% | 97.33% |
| 4.1 tokenizer | 95.83% | 97.92% |
| 4.1 normalization | 79.37% | 80.95% |

## Meaningful additions

`tests/Version41CoverageTest.php` adds 25 public-API cases:

- Canonical numeric forms at the decimal/exponential thresholds, positive float zero, positive and negative high-precision decimals, and floating-point range extremes. Each checks exact output and exact float round-trip equality.
- Invalid UTF-8 values, keys, and nested values; strict input validation.
- Lenient short rows with partial nested field groups, retaining available values while omitting missing fields.
- Explicit strict-versus-lenient behavior for indentation and record boundaries in objects, arrays, and keyed tables.
- Self-returning `toJSON()` fallback and resource normalization without consuming the resource.

The numeric cases exposed an actual 4.1 bug: fixed-place formatting changed `1.2345678901234567e-6` into `0.00000123456789012346`. The fix moves the decimal point in PHP's round-trippable JSON mantissa instead of rounding again. It is confined to the 4.1 codec. The legacy codec and its compatibility snapshots remain unchanged.

The complete coverage run passes **2,105 tests and 9,999 assertions**. The statement denominator increased because the precision fix introduces additional formatting logic.

## Remaining low coverage

- `Constants.php` has 0% line coverage because its only counted executable statement is its private constructor. Testing it through reflection would add no behavioral assurance.
- `helpers.php` reports 66.67%: its uncovered lines are the function-existence guards evaluated during Composer bootstrap, before per-test coverage collection starts. Helper behavior is tested separately.
- The 4.1 normalizer's remaining uncovered lines are private construction, list-precondition guards, and the unused `isArrayOfObjects()` helper. Testing these internals directly would inflate the percentage without testing supported API behavior. Removing unused internal code could be considered separately.
- Most remaining primitive-encoder gaps are defensive error/fallback branches after normalization or branches constrained by PHP's numeric formatting. They should not be forced through private-method reflection or unrealistic inputs just to reach 100%.
- Coverage does not prove spec conformance or all combinations of parser decisions. The pinned official fixtures, compatibility snapshots, and explicit regression cases remain the primary behavioral checks.

## Reproduce

With a PHP executable that has PCOV installed:

```sh
XDEBUG_MODE=off /opt/homebrew/bin/php -d pcov.enabled=1 -d memory_limit=512M \
  vendor/bin/phpunit \
  --coverage-clover coverage/after.xml \
  --coverage-html coverage/after \
  --coverage-text=coverage/after.txt
```

The local before/after HTML reports are `coverage/before/index.html` and `coverage/after/index.html`. Generated reports remain ignored by Git.
