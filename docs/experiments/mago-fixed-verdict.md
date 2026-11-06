# Corrected Mago experiment: adopt the switch

6 November 2026. **Verdict: yes, adopt Mago with the corrections on this branch, pinned to 1.57.0.** The corrected configuration passes its gates, preserves the tested codec behavior, catches the seeded defects, and completes warm checks in roughly half the time. The original PR head should not be merged without the corrections.

Local branch `experiment/mago-fixed` is based on PR #7 at `9759cc5bd61911c1091ec38cf5f057e98d09783b`. Main remains at released `6c9163b`. Nothing has been pushed or merged.

## Corrections

- Restored assignment to the deterministic generator's by-reference state. Document providers again produce **150 distinct values** across 1,800 cases; float providers produce **300 distinct values** across 300 cases. Added a diversity regression test.
- Restored the strict key-encoding closure. Default legacy encoding again throws the same exception class for integer field keys, matching main. Added public-behavior regression tests for tables, mixed lists, and predicates accepting integer keys.
- Replaced incorrect string-only key annotations with `array-key` throughout the affected legacy predicates and encoder contracts. Three real legacy argument mismatches are documented with exact `@mago-expect` exceptions, alongside the one lint exception needed to retain the closure. They preserve known released behavior rather than pretending the inputs cannot occur. [Mago documents these checked expectations](https://mago.carthage.software/1.53.0/en/fundamentals/suppressing-issues/).
- Enabled `check-missing-type-hints = true` and supplied the missing provider/array PHPDoc types. The fixture provider describes unvalidated JSON fields as mixed; it does not claim stronger types than its boundary establishes.

The existing fixture-reader exceptions from the PR remain confined to its two harness files. They can be improved with a checked fixture loader later. No blanket analyzer baseline or new runtime dependencies were added.

## Verification

| Check | Result |
| --- | --- |
| Full suite, PHP 8.1, 8.2, 8.3, 8.4, 8.5 | **4,830 tests / 87,084 assertions** on each version |
| Optional-extension-free codec smoke checks | Both opt-in codecs pass on all five PHP versions |
| Mago 1.57 format, lint, full analysis | All exit zero |
| Seven seeded correctness defects | Both Mago and PHPStan detect all seven |
| Missing array element types | Both detect, with the candidate's actual checking policy |
| Four valid controls, including guarded regex output | Both accept; runtime controls pass |
| Deliberately broken generator | New diversity test fails |
| Deliberately reintroduced callable rewrite | New legacy behavior test fails |

The seeded defects cover return types, unknown methods, nullability, array shapes, lists, unsafe mixed returns, and exhaustive version matching. This is useful targeted evidence, not proof that the analyzers detect exactly the same bugs generally. Mago also detects the PHP 8.2 syntax canary against its configured PHP 8.1 target; PHPStan needs an explicit target to do the same when running on newer PHP.

The full suite includes pinned version fixtures, frozen legacy snapshots, and Mate compatibility tests. Reintroducing either observed regression makes the new tests fail. Temporary mutations were restored before the final timing and checks.

## Performance

Ten alternating warm workflow samples, preceded by warmups, on the same Mac and PHP 8.4 binary. The final run was isolated from test runs and source edits. Every measured command succeeded.

| Workflow | Median | Observed range |
| --- | ---: | ---: |
| Existing Pint check + PHPStan source analysis | **0.673 s** | 0.544–1.148 s |
| Mago format check + lint + full source/test analysis | **0.323 s** | 0.303–0.695 s |

That is **2.09× faster**, or about **52% less wall time**, despite Mago additionally linting and analyzing tests. An earlier corrected run measured 1.83×; normal machine load affects absolute timings. These are local warm developer-tool checks, not installation times or codec throughput measurements. Linux CI performance has not been measured.

Raw results: [paired timings](mago-data/paired-warm.json), [analyzer probes](mago-data/results-1.57.0.json), [final checks](mago-data/final-checks.json), [PHP matrix](mago-data/php-matrix.json), [generator values](mago-data/providers-fixed.json), [behavior probes](mago-data/behavior-fixed.json), [regression controls](mago-data/regression-controls.json).

## Adoption decision

Replace Pint and PHPStan with the corrected Mago 1.57 configuration. The earlier recommendation to retain PHPStan was provisional: at that point the candidate gates were red, the missing-type policy was disabled, and runtime compatibility had not been established for the cleanup. This branch resolves those specific concerns and provides regression checks against the two observed semantic mistakes.

Keep the exact tool pin initially. The previous review found a guarded-regex false positive in 1.59, so upgrading should be a separate checked change. Require the repository's normal Linux CI before merging the corrected PR. Review automatic fixes like ordinary code changes; the generator regression demonstrates why a passing gate alone is insufficient.

To reproduce the paired timing from this worktree:

```sh
TOON_BASELINE=/Users/helge/code/toon-php \
TOON_BENCH_PHP='/Users/helge/Library/Application Support/Herd/bin/php84' \
TOON_BENCH_MODE=paired TOON_BENCH_RUNS=10 \
ruby tests/Support/compare-tooling.rb
```

For the isolated analyzer probes, use the same paths with `TOON_BENCH_SKIP_TIMING=1` instead of `TOON_BENCH_MODE=paired`. The harness removes its temporary PHP probes when done.
