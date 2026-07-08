<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests\Spec;

use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

/**
 * Round-trip regression coverage for strings that resemble TOON structural
 * syntax: brackets, braces, colons, delimiters, hyphens and control characters.
 * Any valid value MUST survive encode -> decode unchanged in every structural
 * context (root scalar, object value, array element, tabular cell, list item,
 * nested, and as an object key).
 *
 * Regression (v3.2.0): the header detector treated a '[' inside a quoted value
 * (e.g. "a[3]b", or an ANSI-coloured log line "\e[31m...") as an array-length
 * header and threw "Unterminated quoted string" on decode. v3.1.0 round-tripped
 * these correctly.
 */
final class RoundTripSpecialStringsTest extends TestCase
{
    private const ESC = "\x1b";

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function specialStrings(): iterable
    {
        $cases = [
            'bracket' => 'a[3]b',
            'inline-header-look' => 'a[3]: b',
            'array-header-look' => '[1]',
            'tabular-header-look' => '[2]{a,b}',
            'keyed-header-look' => 'x[0]:',
            'open-bracket' => 'a[b',
            'brace' => '{a}',
            'open-brace' => 'a{b',
            'close-bracket' => 'a]b',
            'close-brace' => 'a}b',
            'colon' => 'a:b',
            'colon-space' => 'a: b',
            'comma' => 'a,b',
            'pipe' => 'a|b',
            'tab' => "a\tb",
            'quote' => 'a"b',
            'backslash' => 'a\\b',
            'ansi-color' => self::ESC.'[31mred'.self::ESC.'[0m',
            'ansi-in-text' => 'start'.self::ESC.'[31merr'.self::ESC.'[0m',
            'control-only' => self::ESC,
            'hyphen-item' => '- item',
            'leading-space' => ' lead',
            'trailing-space' => 'trail ',
            'reserved-true' => 'true',
            'numeric-string' => '42',
            'combo' => 'x[1]: y, z',
            'bracket-comma' => 'a[1],b[2]',
        ];

        foreach ($cases as $name => $value) {
            yield $name => [$value];
        }
    }

    /**
     * @dataProvider specialStrings
     */
    public function test_round_trips_as_root_scalar(string $s): void
    {
        $this->assertRoundTrip($s);
    }

    /**
     * @dataProvider specialStrings
     */
    public function test_round_trips_as_object_value(string $s): void
    {
        $this->assertRoundTrip(['k' => $s]);
    }

    /**
     * @dataProvider specialStrings
     */
    public function test_round_trips_with_sibling_keys(string $s): void
    {
        $this->assertRoundTrip(['before' => 1, 'k' => $s, 'after' => 2]);
    }

    /**
     * @dataProvider specialStrings
     */
    public function test_round_trips_in_inline_array(string $s): void
    {
        $this->assertRoundTrip(['arr' => [$s, 'plain', $s]]);
    }

    /**
     * @dataProvider specialStrings
     */
    public function test_round_trips_in_tabular_rows(string $s): void
    {
        $this->assertRoundTrip(['rows' => [['a' => $s, 'b' => 1], ['a' => 'x', 'b' => 2]]]);
    }

    /**
     * @dataProvider specialStrings
     */
    public function test_round_trips_in_list_of_objects(string $s): void
    {
        $this->assertRoundTrip(['items' => [['name' => $s, 'extra' => [1, 2]], ['name' => 'y', 'extra' => [3]]]]);
    }

    /**
     * @dataProvider specialStrings
     */
    public function test_round_trips_when_nested(string $s): void
    {
        $this->assertRoundTrip(['outer' => ['inner' => $s]]);
    }

    /**
     * @dataProvider specialStrings
     */
    public function test_round_trips_as_object_key(string $s): void
    {
        // Numeric-looking keys (e.g. '42', '0') are coerced by PHP to int keys,
        // but symmetrically on both sides of the round-trip, so they still
        // compare equal. No case needs to be skipped.
        $this->assertRoundTrip([$s => 'value']);
    }

    private function assertRoundTrip(mixed $value): void
    {
        $encoded = Toon::encode($value);
        $decoded = Toon::decode($encoded);

        $this->assertSame(
            $value,
            $decoded,
            "Round-trip mismatch.\nEncoded:\n".$encoded."\n"
        );
    }
}
