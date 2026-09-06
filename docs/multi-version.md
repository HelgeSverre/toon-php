# Multiple TOON syntax versions

The Composer package version and the TOON wire-format version are independent. Existing calls continue using the released 3.3 compatibility profile. TOON 4.1 is selected explicitly per operation; there is no global setting, automatic detection, or fallback between grammars.

```php
use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\SpecVersion;
use HelgeSverre\Toon\Toon;

// Existing behavior, including associative-array decoding, stays unchanged.
$legacy = Toon::encode($data);
$value = Toon::decode($legacy);

$encoding = new EncodeOptions(version: SpecVersion::V4_1);
$decoding = new DecodeOptions(version: SpecVersion::V4_1);
$document = Toon::encode($data, $encoding);
$value = Toon::decode($document, $decoding);
$valid = Toon::validate($document, $decoding);

// Options are immutable; copies retain the selected version.
$compact41 = EncodeOptions::compact()->withVersion(SpecVersion::V4_1);
$lenient41 = $decoding->withStrict(false);
```

The existing `indent`, `delimiter`, and `strict` constructor arguments retain their names, positions, and defaults. `version` is appended. Existing option factories and convenience helpers continue selecting 3.3; helpers accepting an options object also support explicit 4.1. Leniency controls validation within the selected grammar, never the grammar version.

Applications should agree on the version out of band, for example in an API contract or stored document metadata. A `#hello` root string is content in the legacy profile but a comment in 4.1. Trying another decoder after a failure would not resolve this ambiguity.

## Implementation boundaries

`Toon` selects a `Codec\Codec` implementation once per call. `Codec\V33` wraps the original encoder, normalizer, and decoder classes without changing their behavior or moving their public class names. This preserves consumers that directly use those classes. Those legacy helpers remain tied to 3.3 even if passed a versioned options object; select versions through `Toon` or the public convenience functions.

`Codec\V41` owns its normalization, encoding, fields, and decoder code under `src/Codec/V41/`. These classes are internal. The two implementations share options, exceptions, constants, and line writing. Grammar-specific behavior is deliberately separate: fixes to comment handling, scopes, whitespace, and object encoding in 4.1 do not silently change legacy output.

To add a supported version:

1. Pin its specification and official fixtures, retaining licenses and revision metadata.
2. Add a `SpecVersion` case and a codec implementation; reuse an existing codec only when its behavior is proven identical.
3. Add the explicit dispatch arm in `Toon::codec()`.
4. Extend fixture and version-isolation tests. The fixture provider discovers enum cases automatically and expects a matching version directory.
5. Document syntax differences and compatibility limits. Do not change the default as part of adding support.

The temporary `refactor/multi-version` integration branch can become main. Separate release branches are appropriate for maintaining old package releases, rather than for selecting supported syntax.

## PHP representation and compatibility limits

Both decoders return PHP associative arrays for TOON objects. Consequently an empty object and an empty list both decode to `[]`, and an object with sequential numeric keys may become a PHP list. Re-encoding a decoded value cannot preserve these distinctions. This is the existing PHP API contract, not a lossless JSON AST. Fixture comparisons explicitly project decoded objects to associative arrays; encoding fixtures retain JSON objects as `stdClass` so object/list mistakes cannot pass unnoticed.

The 4.1 encoder preserves empty and numeric-key objects internally. Use `new stdClass()` or `(object) [...]` when encoding those distinctions. Its normalizer retains the established PHP conversion conventions for dates, enums, public properties, and serialization hooks. As with the existing encoder, inputs must form finite, acyclic data structures and serialization hooks must terminate.

The 3.3 profile is compatibility-preserving rather than a claim of perfect spec conformance. [Known fixture differences](legacy-fixture-deviations.md) record 29 discrepancies against the 389 upstream cases, including optional key folding/path expansion that the package does not expose. These behaviors are frozen against release 3.2.1 instead of being silently corrected during the refactor.

## Verification

`vendor/bin/phpunit` runs the existing suite plus:

- 538 pinned official 4.1 encode/decode fixtures, including malformed-input expectations and round trips for all 179 encoding cases.
- 389 pinned official 3.3 inputs compared with results captured from release 3.2.1, both with explicit 3.3 selection and with version omitted.
- Nine interoperability regression cases covering BOM strings, space-only token trimming, and scope validation.
- Version selection, option-copy, PHP normalization, object-encoding, and Unicode boundary tests.
- Compatibility tests using Symfony Mate's actual response encoder, vendored with its MIT license and revision metadata.

Run `php -n tests/Support/check-runtime.php` to check the 4.1 path without Composer polyfills or optional extensions. The package retains PHP 8.1+ and no runtime package dependencies. CI runs the full suite and this smoke check on PHP 8.1–8.5, plus Pint and PHPStan level 9.

Fixture provenance is under `tests/Fixtures/spec/{3.3,4.1}/provenance.json`. The specifications are under `docs/specs/`; `docs/SPEC.md` remains the legacy document. Upstream fixtures and the Mate source are vendored verbatim and excluded from formatting.

The legacy snapshot generator is `tests/Support/capture-legacy.php`. Supply a checkout of commit `55ca6ba93846bde75eeb04dac70a99fe0bb435dd`; the generator verifies source hashes before running. Never regenerate the baseline from the branch under test to make failures disappear.

For downstream validation, Symfony Mate commit `b21be9c4e71236679d452f11e95400e042046afc` was installed in a separate checkout with a Composer path repository pointing to this branch, locally aliased as `3.2.1` to satisfy Mate's existing `^3.2.1` requirement. Its `tests/Encoding` and `tests/Command` suites passed: 159 tests, 561 assertions on PHP 8.5.5. This checks current call sites without changing Mate's production source or package constraint; it does not establish compatibility with every downstream application.

The completed local validation ran 2,074 tests and 9,942 assertions on each of PHP 8.1–8.5. Pint, PHPStan level 9, and the extension-free 4.1 smoke check also passed. The 4.1 implementation was adapted from PR #6 at commit `6e10285ae360d41d80574e65e633687843214fdc`, with corrections for object identity during encoding, BOM quoting, token whitespace, and trailing scope content.
