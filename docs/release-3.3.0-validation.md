# Release 3.3.0 validation

Validated November 6, 2026, for the multi-version integration. The package release is 3.3.0; the default wire-format profile remains the release 3.2.1 compatibility behavior. TOON 4.1 and 4.4 require explicit selection.

## Package checks

The full PHPUnit suite passes on PHP 8.1.34, 8.2.35, 8.3.36, 8.4.27 and 8.5.12: **4,826 tests and 86,711 assertions on each runtime**. Both opt-in codecs pass `php -n tests/Support/check-runtime.php` on every runtime. Pint, PHPStan level 9, `composer validate --strict` and `git diff --check` pass.

All 23 pinned 4.4 fixture JSON files and the specification, changelog, versioning document and license were compared byte-for-byte with upstream tag `v4.4.0`, revision `d09c3d33e37389345a74bd96981eb96dd9be6c5e`. The normal suite exercises all 564 official 4.4 cases and the pinned older-version and released-behavior fixtures.

The October mutation results in [the earlier report](conformance-mutation-review.md) are historical. They were not rerun for this release. Their original temporary reports were not recovered; no new mutation score is claimed.

## Symfony Mate compatibility

Fresh checkout: [symfony/ai-mate at `a00fb617e3823142c1eebe1a26793accd8239ba6`](https://github.com/symfony/ai-mate/commit/a00fb617e3823142c1eebe1a26793accd8239ba6). Tests ran on PHP 8.5.10 with PHPUnit 11.5.57. Its response encoder is byte-identical to the licensed copy already vendored in `tests/Fixtures/mate/`.

First installed Mate's dependencies with released `helgesverre/toon` v3.2.1 at `55ca6ba93846bde75eeb04dac70a99fe0bb435dd`. Then added a Composer path repository pointing to this checkout with a local 3.3.0 version override and ran `composer update helgesverre/toon`. Composer changed exactly one installed package; Mate's original `^3.2.1` constraint and production/test source were retained.

| Check | Released 3.2.1 | Candidate 3.3.0 |
| --- | --- | --- |
| Mate `tests/Encoding` and `tests/Command` | 174 tests, 620 assertions; pass | Same; pass |
| Full standalone Mate suite, random seed 44 | 389 tests, 1,122 assertions; 4 errors, 1 failure | Same tests, assertions and five problems |
| Response probe: 12 payloads, normal/untrusted envelopes, decode/tryDecode, six raw-text cases | Captured baseline | Byte-identical serialized result, including PHP types and key order |
| Actual CLI: capabilities, tool list, tool inspection, server-info call, all in TOON format | Captured baseline | All four outputs byte-identical |

The five full-suite problems are in `SkillReferenceIntegrityTest`: the split Mate repository lacks the `src/Bridge/Symfony/Profiler/Service/Formatter` and `src/Bridge/Symfony/skills` directories, and its guidance references two missing Symfony bridge tools. They occur with the released dependency too. No Mate test or source was changed to suppress them. This is evidence of unchanged downstream behavior, not a claim that Mate's entire standalone suite is green.

The released-behavior snapshot was independently regenerated into a temporary file from the pinned v3.2.1 Git archive using `tests/Support/capture-legacy.php`. Its source-hash verification passed and the result was byte-identical to `tests/Fixtures/legacy-3.2.1.json`; the checked-in snapshot was not rewritten.

### Packaged installation in a fresh Symfony project

A separate disposable Composer project installed `symfony/framework-bundle:^7.4`, the same Mate revision as a mirrored vendor package, and TOON from the candidate's `git archive` ZIP. Mate's Composer plugin and `vendor/bin/mate init --no-interaction` completed successfully. The project's TOON constraint remained `^3.2.1`.

A project-defined `#[MateTool]` returned an untrusted envelope containing an application `_security_notice`, a colon/backslash-containing key, multiline comment-like text, numeric/boolean strings, an empty array and a keyed users object. Running `vendor/bin/mate tools:call release-probe --format=toon` produced the expected decoded array with exact PHP types and key order, and kept the application notice nested inside the trusted envelope.

Swapping only TOON to released 3.2.1 and running the same tool and `probe-mate.php` again produced byte-identical outputs. This exercises an installed distribution archive and the real consumer CLI in addition to the source-checkout tests. The temporary application used no model-provider credentials or API calls.

### Reproduce the comparison

Clone the pinned Mate revision into a disposable directory and install dependencies using `composer install --no-interaction --prefer-dist --no-scripts --no-plugins`. Confirm `composer show helgesverre/toon` selects 3.2.1 for the baseline. Preserve its lock file while swapping only TOON.

Run before and after the dependency swap:

```sh
# From the Mate checkout:
php vendor/bin/phpunit tests/Encoding tests/Command --no-coverage --do-not-cache-result --no-progress
php vendor/bin/phpunit --no-coverage --do-not-cache-result --no-progress --random-order-seed=44
php /path/to/toon-php/tests/Support/probe-mate.php vendor/autoload.php > /tmp/mate-probe.bin
php bin/mate debug:capabilities --format=toon
php bin/mate tools:list --format=toon
php bin/mate tools:inspect server-info --format=toon
php bin/mate tools:call server-info --format=toon
```

Save baseline and candidate outputs separately and compare with `cmp`. The probe serializes its results so numeric PHP types and array order remain observable. No request to a paid model provider is needed. Other dependencies may evolve on a fresh resolution; use the same lock file for both sides of each comparison.

For a local candidate, add this repository entry to Mate's Composer configuration without changing its TOON requirement:

```json
{
  "type": "path",
  "url": "/path/to/toon-php",
  "options": { "versions": { "helgesverre/toon": "3.3.0" } }
}
```

Then run `composer update helgesverre/toon --no-interaction --no-scripts --no-plugins` and confirm Composer changes only that package. After publication, the ordinary tagged package should satisfy the same `^3.2.1` constraint without an alias or path repository.
