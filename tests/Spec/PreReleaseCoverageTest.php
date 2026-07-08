<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests\Spec;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\Toon;
use JsonSerializable;
use PHPUnit\Framework\TestCase;
use Stringable;

/** @internal Test fixture: a backed enum whose values require string quoting (§7.2). */
enum QuotingEnum: string
{
    case YES = 'true';
    case COMMA = 'a,b';
    case NUM = '42';
}

/**
 * Pre-release coverage for gaps found during the test-gap sweep:
 * validate()/decode() parity, Normalize edge cases, non-comma delimiter
 * round-trips, and the previously-untested global helpers.
 */
final class PreReleaseCoverageTest extends TestCase
{
    // ---- validate() ⇔ decode() parity (must always agree) ----

    /**
     * @dataProvider strictInvalidDocuments
     */
    public function test_validate_rejects_what_decode_rejects(string $toon): void
    {
        $this->assertFalse(Toon::validate($toon), "validate() should reject: {$toon}");
        $this->assertFalse($this->decodeSucceeds($toon), "decode() should also reject: {$toon}");
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function strictInvalidDocuments(): array
    {
        return [
            // duplicate sibling keys (§8/§14.4)
            'dup root keys' => ["id: 1\nid: 2"],
            'dup nested keys' => ["u:\n  a: 1\n  a: 2"],
            'dup keys in list-item object' => ["[1]:\n  - id: 1\n    id: 2"],
            'dup keyed inline arrays' => ["n[2]: 1,2\nn[2]: 3,4"],
            // malformed bracket keys (§6/§14.2)
            'stray close bracket' => ['a]: x'],
            'non-numeric length' => ['foo[bar]: x'],
            'leading-zero length' => ['foo[03]: x'],
            'negative length' => ['foo[-1]: x'],
            'content after bracket' => ['foo[2]extra: x'],
            'stray open bracket' => ['a[: x'],
            // malformed nested list/tabular items (§9)
            'virtual list item bad depth' => ["[1]:\n  - u[2]:\n        - a\n        - b"],
            'virtual tabular row width' => ["[1]:\n  - u[1]{a,b,c}:\n      1,2"],
            'virtual tabular count' => ["[1]:\n  - u[3]{a}:\n      1\n      2"],
        ];
    }

    public function test_validate_accepts_over_indented_list_item_field(): void
    {
        // decode() tolerantly re-nests an over-indented field; validate() must agree.
        $toon = "[1]:\n  - id: 1\n      x: 2";
        $this->assertTrue(Toon::validate($toon));
        $this->assertSame([['id' => ['x' => 2]]], Toon::decode($toon));
    }

    public function test_validate_lenient_accepts_duplicate_keys(): void
    {
        // Lenient mode applies last-write-wins, so the document is valid.
        $lenient = DecodeOptions::lenient();
        $this->assertTrue(Toon::validate("id: 1\nid: 2", $lenient));
        $this->assertSame(['id' => 2], Toon::decode("id: 1\nid: 2", $lenient));
    }

    public function test_helper_validate_matches_toon_validate(): void
    {
        $this->assertFalse(toon_validate("id: 1\nid: 2"));
        $this->assertTrue(toon_validate_lenient("id: 1\nid: 2"));
        $this->assertTrue(toon_validate("id: 1\nname: Alice"));
    }

    // ---- Normalize edge cases ----

    public function test_json_serializable_returning_scalar(): void
    {
        $obj = new class implements JsonSerializable
        {
            public function jsonSerialize(): mixed
            {
                return 'just a string';
            }
        };
        // Internal spaces don't force quoting (§7.2), so this stays unquoted.
        $this->assertSame('just a string', Toon::encode($obj));
    }

    public function test_json_serializable_returning_list(): void
    {
        $obj = new class implements JsonSerializable
        {
            public function jsonSerialize(): mixed
            {
                return [1, 2, 3];
            }
        };
        $this->assertSame('[3]: 1,2,3', Toon::encode($obj));
    }

    public function test_enum_backing_value_is_quoted_when_required(): void
    {
        // The backing value goes through §7.2 quoting rules like any other string.
        $this->assertSame('"true"', Toon::encode(QuotingEnum::YES));
        $this->assertSame('v: "a,b"', Toon::encode(['v' => QuotingEnum::COMMA]));
        $this->assertSame('"42"', Toon::encode(QuotingEnum::NUM));
        // Round-trip: the value comes back as the string "true", not boolean true.
        $this->assertSame('true', Toon::decode(Toon::encode(QuotingEnum::YES)));
    }

    public function test_stringable_object_without_public_properties_encodes_empty(): void
    {
        // Characterization: Normalize has no Stringable branch; public props only.
        $obj = new class implements Stringable
        {
            public function __toString(): string
            {
                return 'ignored';
            }
        };
        $this->assertSame('', Toon::encode($obj));
    }

    public function test_object_with_only_private_properties_encodes_empty(): void
    {
        $obj = new class
        {
            private int $hidden = 1;
        };
        $this->assertSame('', Toon::encode($obj));
    }

    /**
     * @dataProvider floatRoundTrips
     */
    public function test_float_exponent_round_trip(float|int $value, string $encoded): void
    {
        $this->assertSame($encoded, Toon::encode($value));
        $this->assertSame($value, Toon::decode(Toon::encode($value)));
    }

    /**
     * @return array<string, array{0: float|int, 1: string}>
     */
    public static function floatRoundTrips(): array
    {
        return [
            'tiny' => [1e-7, '1e-7'],
            'huge' => [1e21, '1e+21'],
            'precision' => [0.1 + 0.2, '0.30000000000000004'],
        ];
    }

    // ---- Non-comma delimiter decode / round-trip ----

    public function test_pipe_delimiter_round_trip_with_comma_values(): void
    {
        $value = ['items' => ['a,b', 'c,d']];
        $encoded = Toon::encode($value, EncodeOptions::pipeDelimited());
        $this->assertSame('items[2|]: a,b|c,d', $encoded);
        $this->assertSame($value, Toon::decode($encoded));
    }

    public function test_tab_delimiter_round_trip_with_comma_values(): void
    {
        $value = ['items' => ['a,b', 'c,d']];
        $encoded = Toon::encode($value, EncodeOptions::tabular());
        $this->assertSame($value, Toon::decode($encoded));
    }

    public function test_pipe_preset_tabular_round_trip(): void
    {
        $value = [
            'users' => [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']],
            'meta' => ['count' => 2, 'tags' => ['a', 'b']],
        ];
        $this->assertSame($value, Toon::decode(Toon::encode($value, EncodeOptions::pipeDelimited())));
    }

    // ---- Previously-untested global helpers ----

    public function test_toon_compare_returns_size_breakdown(): void
    {
        $result = toon_compare(['users' => [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']]]);
        $this->assertSame(['toon', 'json', 'savings', 'savings_percent'], array_keys($result));
        $this->assertIsInt($result['toon']);
        $this->assertIsInt($result['json']);
        $this->assertSame($result['json'] - $result['toon'], $result['savings']);
        $this->assertStringEndsWith('%', (string) $result['savings_percent']);
    }

    public function test_toon_size_matches_encoded_length(): void
    {
        $value = ['a' => 1, 'b' => [1, 2, 3]];
        $this->assertSame(strlen(Toon::encode($value)), toon_size($value));
    }

    public function test_toon_estimate_tokens_is_size_over_four(): void
    {
        $value = ['a' => 1, 'b' => 'hello world'];
        $this->assertSame((int) ceil(toon_size($value) / 4), toon_estimate_tokens($value));
    }

    public function test_toon_decode_lenient_tolerates_count_mismatch(): void
    {
        // Lenient decoding ignores the declared-vs-actual length mismatch.
        $this->assertSame(['a', 'b'], toon_decode_lenient('[3]: a,b'));
        $this->assertSame(['a' => 1, 'b' => 2], toon_decode_lenient("a: 1\nb: 2"));
    }

    private function decodeSucceeds(string $toon, ?DecodeOptions $options = null): bool
    {
        try {
            Toon::decode($toon, $options);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
