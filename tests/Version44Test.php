<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\SpecVersion;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Version44Test extends TestCase
{
    public function test_selection_and_option_copies_do_not_change_existing_versions(): void
    {
        $encode = new EncodeOptions(4, '|');
        $decode = new DecodeOptions(4, false);
        self::assertEquals(new EncodeOptions(1, "\t", SpecVersion::V4_4), $encode->withVersion(SpecVersion::V4_4)->withIndent(1)->withDelimiter("\t"));
        self::assertEquals(new DecodeOptions(1, true, SpecVersion::V4_4), $decode->withVersion(SpecVersion::V4_4)->withIndent(1)->withStrict(true));
        self::assertSame(SpecVersion::V3_3, $encode->version);
        self::assertSame(SpecVersion::V3_3, $decode->version);

        for ($i = 0; $i < 3; $i++) {
            foreach ([SpecVersion::V4_1, SpecVersion::V4_4] as $version) {
                $encoding = new EncodeOptions(version: $version);
                $decoding = new DecodeOptions(version: $version);
                self::assertSame('"#hello"', toon('#hello', $encoding));
                self::assertSame([], Toon::decode('#hello', $decoding));
                self::assertTrue(toon_validate("#comment\nx: 1", $decoding));
                self::assertSame('#hello', toon('#hello'));
                self::assertSame('#hello', Toon::decode('#hello'));
                self::assertSame('', Toon::encode([]));
            }
            $document = "[1]{a,b}:\n  1";
            self::assertSame([['a' => 1]], Toon::decode($document, new DecodeOptions(strict: false, version: SpecVersion::V4_1)));
            self::assertFalse(Toon::validate($document, new DecodeOptions(strict: false, version: SpecVersion::V4_4)));
        }
    }

    public static function rejectedInBothModes(): iterable
    {
        yield 'malformed length' => ['items[03]: a,b,c'];
        yield 'bad UTF-8' => ["key: \xFF"];
        yield 'surrogate UTF-8' => ["key: \xED\xA0\x80"];
        yield 'over-indented sibling' => ["a: 1\n  b: 2"];
        yield 'over-indented second list item' => ["items[2]:\n  - a\n    - b"];
        yield 'over-indented second table row' => ["items[2]{id}:\n  1\n    2"];
        yield 'space-only field name' => ['items[0]{ }:'];
        yield 'trailing scalar' => ["[1]: a\nbare"];
        yield 'row width' => ["[1]{a,b}:\n  1"];
        yield 'bad escape' => ['"\\x41"'];
        yield 'text after closing quote' => ['x: "a"tail"'];
        yield 'escaped closing quote' => ['x: "'.'\\'.'"'];
        yield 'surrogate escape' => ['"\\ud800"'];
        yield 'last low surrogate escape' => ['"\\udfff"'];
        yield 'over-indented item field' => ["[1]:\n  - a: 1\n      b: 2"];
        yield 'depth recovery cannot consume a shallower line' => ["a:\n    b: 1\n  c: 2"];
    }

    #[DataProvider('rejectedInBothModes')]
    public function test_non_strict_mode_does_not_relax_other_rules(string $document): void
    {
        foreach ([true, false] as $strict) {
            self::assertFalse(Toon::validate($document, new DecodeOptions(strict: $strict, version: SpecVersion::V4_4)));
        }
    }

    public static function recoveries(): iterable
    {
        yield 'counts never truncate' => ["items[1]:\n  - a\n  - b", ['items' => ['a', 'b']]];
        yield 'duplicate keys keep first position' => ["a: 1\nb: 2\na: 3", ['a' => 3, 'b' => 2]];
        yield 'tab and fractional indentation' => ["a:\n\t b: 1", ['a' => ['b' => 1]]];
        yield 'blank in header span' => ["[2]:\n  - a\n\n  - b", ['a', 'b']];
        yield 'depth jump rebases descendants' => ["a:\n      b:\n        c: 1\n      d: 2\nz: 3", ['a' => ['b' => ['c' => 1], 'd' => 2], 'z' => 3]];
        yield 'duplicate nested fields' => ["[1]{a{x},a{y}}:\n  1,2", [['a' => ['y' => 2]]]];
    }

    #[DataProvider('recoveries')]
    public function test_exact_non_strict_recoveries(string $document, array $expected): void
    {
        self::assertFalse(Toon::validate($document, new DecodeOptions(version: SpecVersion::V4_4)));
        self::assertSame($expected, Toon::decode($document, new DecodeOptions(strict: false, version: SpecVersion::V4_4)));
    }

    public static function canonicalNumbers(): iterable
    {
        yield 'negative zero' => [-0.0, '0'];
        yield 'positive zero' => [0.0, '0'];
        yield 'one' => [1.0, '1'];
        yield 'whole decimal' => [2.0, '2'];
        yield 'negative whole decimal' => [-20.0, '-20'];
        yield 'fractional decimal' => [0.25, '0.25'];
        yield 'small boundary' => [1e-6, '0.000001'];
        yield 'small digits' => [1.2345678901234567e-6, '0.0000012345678901234567'];
        yield 'large digits' => [1.2345678901234567e19, '12345678901234567000'];
        yield 'large boundary' => [1e21, '1e+21'];
        yield 'smallest subnormal' => [5e-324, '5e-324'];
        yield 'negative smallest subnormal' => [-5e-324, '-5e-324'];
        yield 'largest float' => [PHP_FLOAT_MAX, '1.7976931348623157e+308'];
    }

    #[DataProvider('canonicalNumbers')]
    public function test_canonical_floats_ignore_application_precision_and_restore_it(float $value, string $expected): void
    {
        $original = ini_get('serialize_precision');
        try {
            foreach (['3', '17', '-1'] as $precision) {
                ini_set('serialize_precision', $precision);
                $wire = Toon::encode($value, new EncodeOptions(version: SpecVersion::V4_4));
                self::assertSame($precision, ini_get('serialize_precision'));
                self::assertSame($expected, $wire);
                self::assertSame($value === 0.0 ? 0.0 : $value, (float) Toon::decode($wire, new DecodeOptions(version: SpecVersion::V4_4)));
            }
        } finally {
            ini_set('serialize_precision', $original);
        }
    }

    public function test_out_of_range_numeric_policy_and_integer_boundaries(): void
    {
        foreach ([true, false] as $strict) {
            $options = new DecodeOptions(strict: $strict, version: SpecVersion::V4_4);
            self::assertSame('1e999', Toon::decode('1e999', $options));
            self::assertSame('-1e999', Toon::decode('-1e999', $options));
            self::assertSame(0.0, Toon::decode('1e-999', $options));
            self::assertSame(PHP_INT_MAX, Toon::decode((string) PHP_INT_MAX, $options));
            self::assertSame(PHP_INT_MIN, Toon::decode((string) PHP_INT_MIN, $options));
            self::assertSame(1.0, Toon::decode('1.0', $options));
        }
    }

    public function test_keyed_rows_need_no_space_after_the_colon(): void
    {
        foreach ([true, false] as $strict) {
            $options = new DecodeOptions(strict: $strict, version: SpecVersion::V4_4);
            self::assertSame(['k' => ['u' => ['id' => 42]]], Toon::decode("k[1:]{id}:\n  u:42", $options));
        }
    }

    public function test_number_prefix_in_a_string_does_not_require_quotes(): void
    {
        $options = new EncodeOptions(version: SpecVersion::V4_4);
        self::assertSame('1st', Toon::encode('1st', $options));
        self::assertSame('1eagle', Toon::encode('1eagle', $options));
    }

    public function test_error_lines_count_comments_and_blank_lines(): void
    {
        try {
            Toon::decode("#comment\n\nk[03]: 1", new DecodeOptions(version: SpecVersion::V4_4));
            self::fail('Malformed header was accepted');
        } catch (\HelgeSverre\Toon\Exceptions\DecodeException $error) {
            self::assertSame(3, $error->getToonLine());
            self::assertSame('k[03]: 1', $error->getSnippet());
        }
    }

    public function test_empty_and_numeric_key_objects_remain_objects_during_encoding(): void
    {
        $options = new EncodeOptions(version: SpecVersion::V4_4);
        self::assertSame('', Toon::encode(new \stdClass, $options));
        self::assertSame('empty:', Toon::encode(['empty' => new \stdClass], $options));
        self::assertSame('"0": value', Toon::encode((object) ['0' => 'value'], $options));
        self::assertSame("[2:]{\"0\"}:\n  a: x\n  b: y", Toon::encode((object) ['a' => (object) ['0' => 'x'], 'b' => (object) ['0' => 'y']], $options));
        self::assertSame("[2]:\n  -\n  -", Toon::encode([new \stdClass, new \stdClass], $options));
    }

    public function test_newline_in_a_key_is_quoted_and_retained(): void
    {
        $input = ["a\n" => 1];
        $wire = Toon::encode($input, new EncodeOptions(version: SpecVersion::V4_4));
        self::assertSame('"a\\n": 1', $wire);
        self::assertSame($input, Toon::decode($wire, new DecodeOptions(version: SpecVersion::V4_4)));
    }

    public static function invalidHostStrings(): iterable
    {
        yield 'invalid value' => ["\xFF"];
        yield 'invalid array key' => [["\xFF" => 1]];
        yield 'invalid object key' => [(object) ["\xFF" => 1]];
        yield 'nested surrogate' => [[['value' => "\xED\xA0\x80"]]];
        yield 'truncated sequence' => ["\xF0\x9F\x98"];
    }

    #[DataProvider('invalidHostStrings')]
    public function test_encoder_rejects_host_strings_that_are_not_unicode(mixed $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Toon::encode($value, new EncodeOptions(version: SpecVersion::V4_4));
    }

    public function test_self_returning_hook_falls_back_to_properties_and_resources_remain_open(): void
    {
        $value = new class
        {
            public string $name = 'Ada';

            public function toJSON(): self
            {
                return $this;
            }
        };
        $options = new EncodeOptions(version: SpecVersion::V4_4);
        self::assertSame('name: Ada', Toon::encode($value, $options));
        $stream = fopen('php://memory', 'r+');
        try {
            fwrite($stream, 'retained');
            rewind($stream);
            self::assertSame('stream: null', Toon::encode(['stream' => $stream], $options));
            self::assertSame('retained', stream_get_contents($stream));
        } finally {
            fclose($stream);
        }
    }
}
