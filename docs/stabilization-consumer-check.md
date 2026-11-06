# Benchmark and consumer stabilization check

Validated 6 November 2026. No version bump, tag or release was created.

## Benchmark workflow

Pull requests now run nine representative workloads on PHP 8.3 instead of the
full five-version matrix plus repeated full comparison/regression runs. Both
measurements use the exact base revision's benchmark definitions and PHPBench
installation, with separate base/candidate codec autoloaders on one runner.

The gate reads PHPBench XML directly and compares median microseconds per
revolution, keyed by benchmark, subject and canonicalized parameter set. A
slowdown greater than 15% exits 1. Invalid or missing measurements exit 2;
missing variants, benchmark errors, invalid timing values and insufficient
samples cannot silently pass. An unchanged comparison exits 0. CLI controls
verified all three outcomes, including a deliberately 20% slower result.
An end-to-end control also added a 1 ms encode delay in a disposable codec copy.
Running the base benchmark harness with that candidate bootstrap changed the
median from 37.020 µs to 1,302.720 µs; the real comparison exited 1 as required.
The production codec source was unchanged.

The report is saved in the job summary and uploaded with its raw measurements.
A separate reporting job has PR write permission and updates one bot comment;
jobs executing benchmark code have only content-read permission, with checkout
credentials not persisted. Fork and Dependabot PRs retain summaries/artifacts
without attempting to post using a read-only token. This follows GitHub's
[job-scoped permission model](https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-syntax).

The full PHP 8.1–8.5 matrix is available through `workflow_dispatch` and existing
version-tag triggers. Requesting a manual run does not create a release. See
[benchmark documentation](../benchmarks/performance/README.md#ci-integration)
for reproduction, sampling settings and exit codes.

The first new PR workflow completed in about 72 seconds, compared with roughly
23 minutes for the previous workflow. Its nine measured changes ranged from
-1.32% to +1.46%. The bot successfully posted the report, and the raw results
artifact was uploaded. Timing provenance is in
[pr-benchmark-run.json](stabilization-data/pr-benchmark-run.json).

## Fresh Symfony Mate consumer

Fresh source checkout: `symfony/ai-mate` revision
`dada7fc756852c6219131412a37028509470820b`. Runtime: PHP 8.5.10.
Baseline: published TOON 3.3.0 at `6c9163b`. Candidate: Composer-installed
`dev-main` at `455a444`, using the local consumer requirement
`dev-main as 3.3.0`. No package was published for this alias.

Both comparisons preserved the same dependency lock. The sole changed installed
package was `helgesverre/toon`; Mate's source and tests were not modified.

| Check | Released 3.3.0 | Current dev-main |
| --- | --- | --- |
| Mate encoding and command tests | 174 tests / 620 assertions; pass | Same; pass |
| Full standalone Mate suite, seed 44 | 390 tests / 1,127 assertions; 4 errors, 1 failure | Same counts and problems |
| Existing 12-payload response probe, normal/untrusted envelopes and raw text | Captured | Byte-identical serialized output |
| Actual CLI: capabilities, tool list, inspection, server-info call, TOON format | Captured | All four byte-identical |

The full-suite problems occur in `SkillReferenceIntegrityTest`: the standalone
split repository lacks Symfony bridge directories and references two absent
Symfony bridge tools. They reproduce with the released package; they are not
introduced by the candidate. This is not a claim that all standalone Mate tests
pass.

## Project-defined tool through the real CLI

A second disposable Composer project installed `symfony/framework-bundle:^7.4`,
the same Mate source as a mirrored path package, and published TOON 3.3.0.
Mate's Composer plugin and `vendor/bin/mate init --no-interaction` ran normally.
The plugin is currently a beta package, so this disposable project explicitly
allowed `symfony/ai-mate-composer-plugin:^1.0@beta`.

A custom `#[MateTool]` under `mate/src/` returned
`ResponseEncoder::encodeUntrusted()` for four scenarios:

- A two-row user table and integer count.
- Nested data with an application `_security_notice`, a backslash/colon key,
  multiline comment-like text, a boolean and null.
- Numeric/boolean strings alongside actual integers, floats and booleans.
- Empty arrays/objects, blank text and a non-sequential integer-key map.

Each scenario ran through
`vendor/bin/mate tools:call stabilization-probe --scenario=... --format=toon`
before and after swapping only TOON to `dev-main`. Every output and the tool
listing were byte-identical. A separate decode check verified exact expected
PHP values, types and key order, with the application notice retained inside the
untrusted payload. Empty object/list distinctions follow the library's existing
associative-array decoding contract.

No model-provider credentials or paid API calls were used. The exercise tests
the installed consumer CLI and custom tools, rather than just the vendored Mate
encoder fixture. Result hashes and package revisions are in
[mate-comparison.json](stabilization-data/mate-comparison.json); gate controls are
in [benchmark-gate-controls.json](stabilization-data/benchmark-gate-controls.json).

To repeat the source-consumer check, clone the pinned Mate revision, install its
development dependencies, run the commands from
[the previous consumer report](release-3.3.0-validation.md#reproduce-the-comparison),
then swap only TOON with
`composer require --dev 'helgesverre/toon:dev-main as 3.3.0' --no-scripts --no-plugins`.
Compare serialized probe and CLI outputs before/after, and verify the lock-file
diff changes only TOON. Use a separate disposable project for the custom-tool
scenario so its plugin-generated configuration does not affect the source tests.
