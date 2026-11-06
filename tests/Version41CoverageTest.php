<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\SpecVersion;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Version41CoverageTest extends TestCase
{
    public static function numbers(): iterable
    {
        yield 'positive float zero' => [0.0, '0'];
        yield 'below decimal threshold' => [1e-7, '1e-7'];
        yield 'decimal threshold' => [1e-6, '0.000001'];
        yield 'small decimal precision' => [1.2345678901234567e-6, '0.0000012345678901234567'];
        yield 'negative small decimal precision' => [-1.2345678901234567e-6, '-0.0000012345678901234567'];
        yield 'large decimal precision' => [1.2345678901234567e19, '12345678901234567000'];
        yield 'large decimal' => [1e20, '100000000000000000000'];
        yield 'exponential threshold' => [1e21, '1e+21'];
        yield 'negative exponent form' => [-1.25e30, '-1.25e+30'];
        yield 'small exponent precision' => [1.2345678901234567e-20, '1.2345678901234567e-20'];
        yield 'smallest normal float' => [PHP_FLOAT_MIN, '2.2250738585072014e-308'];
        yield 'largest float' => [PHP_FLOAT_MAX, '1.7976931348623157e+308'];
    }

    #[DataProvider('numbers')]
    public function test_numbers_use_canonical_form_without_losing_precision(float $value, string $expected): void
    {
        $wire = Toon::encode($value, new EncodeOptions(version: SpecVersion::V4_1));
        self::assertSame($expected, $wire);
        self::assertSame($value, (float) Toon::decode($wire, new DecodeOptions(version: SpecVersion::V4_1)));
    }

    public static function invalidStrings(): iterable
    {
        yield 'invalid value' => ["\xFF"];
        yield 'invalid key' => [["\xFF" => 1]];
        yield 'surrogate in nested value' => [[['value' => "\xED\xA0\x80"]]];
    }

    #[DataProvider('invalidStrings')]
    public function test_encoder_rejects_non_unicode_host_strings(mixed $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Toon::encode($value, new EncodeOptions(version: SpecVersion::V4_1));
    }

    public function test_strict_decoder_rejects_invalid_utf8(): void
    {
        self::assertFalse(Toon::validate("key: \xFF", new DecodeOptions(version: SpecVersion::V4_1)));
    }

    public function test_lenient_short_rows_preserve_available_nested_fields(): void
    {
        $options = new DecodeOptions(strict: false, version: SpecVersion::V4_1);
        self::assertSame([['id' => 1, 'address' => ['city' => 'Oslo']]], Toon::decode("[1]{id,address{city,zip}}:\n  1,Oslo", $options));
        self::assertSame(['user' => ['id' => 1, 'address' => []]], Toon::decode("[1:]{id,address{city,zip}}:\n  user: 1", $options));
    }

    public static function lenientDocuments(): iterable
    {
        yield 'over-indented object field' => ["a: 1\n    note: ignored\nb: 2", ['a' => 1, 'b' => 2]];
        yield 'over-indented list item' => ["[1]:\n    - ignored\n  - kept", ['kept']];
        yield 'over-indented tabular row' => ["[1]{id,name}:\n    ignored,row\n  1,Ada", [['id' => 1, 'name' => 'Ada']]];
        yield 'over-indented keyed entry' => ["[1:]{id}:\n    ignored: 2\n  kept: 1", ['kept' => ['id' => 1]]];
        yield 'non-row ends tabular rows' => ["[1]{id,name}:\n  1,Ada\n  note: ignored", [['id' => 1, 'name' => 'Ada']]];
        yield 'non-item ends list items' => ["[1]:\n  - kept\n  note: ignored", ['kept']];
    }

    #[DataProvider('lenientDocuments')]
    public function test_leniency_is_explicit_and_preserves_recognized_records(string $document, array $expected): void
    {
        $strict = new DecodeOptions(version: SpecVersion::V4_1);
        self::assertFalse(Toon::validate($document, $strict));
        self::assertSame($expected, Toon::decode($document, $strict->withStrict(false)));
    }

    public function test_self_returning_json_hook_falls_back_to_public_properties(): void
    {
        $value = new class
        {
            public string $name = 'Ada';

            public function toJSON(): self
            {
                return $this;
            }
        };
        self::assertSame('name: Ada', Toon::encode($value, new EncodeOptions(version: SpecVersion::V4_1)));
    }

    public function test_unsupported_resource_is_normalized_without_consuming_it(): void
    {
        $resource = fopen('php://memory', 'r+');
        try {
            fwrite($resource, 'data');
            rewind($resource);
            self::assertSame('stream: null', Toon::encode(['stream' => $resource], new EncodeOptions(version: SpecVersion::V4_1)));
            self::assertSame('data', stream_get_contents($resource));
        } finally {
            fclose($resource);
        }
    }
}
