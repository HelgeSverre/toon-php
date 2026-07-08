<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests\Spec;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

/**
 * Conformance tests for TOON Specification changes v3.1 through v3.3.
 *
 * v3.1:
 * - §7.1: control characters U+0000-U+001F (except \n \r \t) encode as \uXXXX;
 *         decoders MUST decode \uXXXX (case-insensitive), reject lone surrogates.
 * - §15:  control characters are preserved as data, never stripped.
 * - §9.1: decoders MUST accept `key: []`, `[]`, and the legacy `key[0]:` / `[0]:`.
 *
 * v3.2:
 * - §6/§14.2: strict mode rejects leading-zero / malformed bracket lengths ([03]).
 * - §8/§14.4: duplicate sibling keys — strict errors, non-strict last-write-wins.
 *
 * v3.3:
 * - §2: canonical decimal for n = 0 or 1e-6 <= |n| < 1e21; exponent notation
 *       (lowercase e, explicit sign) outside that range.
 */
final class Version31To33ComplianceTest extends TestCase
{
    // ---- v3.1 §7.1 control-character escaping ----

    public function test_encodes_control_characters_as_unicode_escape(): void
    {
        $this->assertEquals('"a\\u0000b"', Toon::encode("a\x00b"));
        $this->assertEquals('"a\\u001bb"', Toon::encode("a\x1bb"));
        // \n \r \t keep their short escapes
        $this->assertEquals('"a\\nb"', Toon::encode("a\nb"));
        $this->assertEquals('"a\\tb"', Toon::encode("a\tb"));
    }

    public function test_control_characters_are_preserved_not_stripped(): void
    {
        $input = "x\x01\x02\x1f\x0b\x0cy";
        $this->assertSame(['s' => $input], Toon::decode(Toon::encode(['s' => $input])));
    }

    public function test_decodes_unicode_escape_case_insensitive(): void
    {
        $this->assertEquals('A', Toon::decode('"\\u0041"'));
        $this->assertEquals('é', Toon::decode('"\\u00E9"'));
        $this->assertEquals('é', Toon::decode('"\\u00e9"'));
    }

    public function test_rejects_incomplete_unicode_escape(): void
    {
        $this->expectExceptionMessage('four hex digits');
        Toon::decode('"\\u12"');
    }

    public function test_rejects_lone_surrogate_unicode_escape(): void
    {
        $this->expectExceptionMessage('lone surrogate');
        Toon::decode('"\\udc00"');
    }

    // ---- v3.1 §9.1 empty-array decoding ----

    public function test_decodes_bare_empty_array_at_root(): void
    {
        $this->assertSame([], Toon::decode('[]'));
    }

    public function test_decodes_canonical_empty_array_field(): void
    {
        $this->assertSame(['items' => []], Toon::decode('items: []'));
    }

    public function test_decodes_legacy_empty_array_forms(): void
    {
        $this->assertSame(['items' => []], Toon::decode('items[0]:'));
        $this->assertSame([], Toon::decode('[0]:'));
    }

    public function test_empty_array_round_trips(): void
    {
        $encoded = Toon::encode(['items' => []]);
        $this->assertSame(['items' => []], Toon::decode($encoded));
    }

    // ---- v3.2 §6 leading-zero / malformed bracket lengths ----

    public function test_strict_rejects_leading_zero_length(): void
    {
        $this->expectExceptionMessage('Malformed array header');
        Toon::decode('items[03]: a,b,c', new DecodeOptions(strict: true));
    }

    public function test_lenient_treats_leading_zero_length_as_literal_key(): void
    {
        $this->assertSame(
            ['items[03]' => 'a,b,c'],
            Toon::decode('items[03]: a,b,c', new DecodeOptions(strict: false)),
        );
    }

    public function test_valid_length_still_parses_as_array(): void
    {
        $this->assertSame(['items' => ['a', 'b', 'c']], Toon::decode('items[3]: a,b,c'));
    }

    // ---- v3.2 §14.4 duplicate sibling keys ----

    public function test_strict_errors_on_duplicate_sibling_keys(): void
    {
        $this->expectExceptionMessage('Duplicate object key');
        Toon::decode("a: 1\na: 2", new DecodeOptions(strict: true));
    }

    public function test_lenient_applies_last_write_wins_for_duplicate_keys(): void
    {
        $this->assertSame(['a' => 2], Toon::decode("a: 1\na: 2", new DecodeOptions(strict: false)));
    }

    // ---- v3.3 §2 number canonical range ----

    public function test_canonical_decimal_within_range(): void
    {
        $this->assertEquals('100000000000000000000', Toon::encode(1e20));
        $this->assertEquals('500000000000000000000', Toon::encode(5e20));
        $this->assertEquals('0.000001', Toon::encode(1e-6));
    }

    public function test_exponent_notation_outside_range(): void
    {
        $this->assertEquals('1e+21', Toon::encode(1e21));
        $this->assertEquals('1e+25', Toon::encode(1e25));
        $this->assertEquals('1e-7', Toon::encode(1e-7));
    }

    /**
     * @dataProvider outOfRangeNumbers
     */
    public function test_out_of_range_numbers_round_trip_as_numbers(float $value): void
    {
        $decoded = Toon::decode(Toon::encode(['n' => $value]));
        $this->assertSame($value, $decoded['n']);
    }

    /**
     * @return iterable<string, array{0: float}>
     */
    public static function outOfRangeNumbers(): iterable
    {
        yield '1e21' => [1e21];
        yield '1e25' => [1e25];
        yield '1e-7' => [1e-7];
        yield 'subnormal' => [5e-324];
        yield 'float min' => [PHP_FLOAT_MIN];
        yield 'epsilon' => [PHP_FLOAT_EPSILON];
    }
}
