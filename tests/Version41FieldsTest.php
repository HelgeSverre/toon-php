<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\SpecVersion;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Version41FieldsTest extends TestCase
{
    public static function fieldCases(): iterable
    {
        $nulRows = [["\0x" => 1], ["\0x" => 2]];
        yield 'NUL-prefixed tabular field' => [$nulRows, '[2]{"\u0000x"}:'."\n  1\n  2", $nulRows];

        $nulEntries = ['first' => $nulRows[0], 'second' => $nulRows[1]];
        yield 'NUL-prefixed keyed field' => [$nulEntries, '[2:]{"\u0000x"}:'."\n  first: 1\n  second: 2", $nulEntries];

        $nestedNul = [["\0x" => ["\0y" => 1]], ["\0x" => ["\0y" => 2]]];
        yield 'NUL-prefixed group and leaf' => [$nestedNul, '[2]{"\u0000x"{"\u0000y"}}:'."\n  1\n  2", $nestedNul];

        $rows = [['1' => 'a', '01' => 'b'], ['01' => 'c', '1' => 'd']];
        $reordered = [['1' => 'a', '01' => 'b'], ['1' => 'd', '01' => 'c']];
        yield 'numeric-looking tabular fields' => [$rows, '[2]{"1","01"}:'."\n  a,b\n  d,c", $reordered];

        yield 'numeric-looking keyed fields' => [
            ['first' => $rows[0], 'second' => $rows[1]],
            '[2:]{"1","01"}:'."\n  first: a,b\n  second: d,c",
            ['first' => $reordered[0], 'second' => $reordered[1]],
        ];

        yield 'numeric-looking nested fields' => [
            [['nested' => $rows[0]], ['nested' => $rows[1]]],
            '[2]{nested{"1","01"}}:'."\n  a,b\n  d,c",
            [['nested' => $reordered[0]], ['nested' => $reordered[1]]],
        ];
    }

    #[DataProvider('fieldCases')]
    public function test_preserves_field_values_and_uses_required_table_form(array $input, string $expected, array $decoded): void
    {
        $wire = Toon::encode($input, new EncodeOptions(version: SpecVersion::V4_1));
        self::assertSame($expected, $wire);
        self::assertSame($decoded, Toon::decode($wire, new DecodeOptions(version: SpecVersion::V4_1)));
    }
}
