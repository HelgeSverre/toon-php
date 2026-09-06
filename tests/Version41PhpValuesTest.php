<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\SpecVersion;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Version41PhpValuesTest extends TestCase
{
    public static function values(): iterable
    {
        yield 'date' => [new \DateTimeImmutable('2026-01-01T00:00:00+00:00'), '"2026-01-01T00:00:00+00:00"'];
        yield 'backed enum' => [SpecVersion::V4_1, '"4.1"'];
        yield 'unit enum' => [Version41Unit::Ready, 'Ready'];
        yield 'nan' => [NAN, 'null'];
        yield 'infinity' => [INF, 'null'];
        yield 'negative zero' => [-0.0, '0'];
        yield 'public properties only' => [new class
        {
            public string $visible = 'yes';

            private string $hidden = 'private';
        }, 'visible: yes'];
        yield 'json serialization retains empty objects' => [new class implements \JsonSerializable
        {
            public function jsonSerialize(): mixed
            {
                return (object) ['empty' => new \stdClass];
            }
        }, 'empty:'];
        yield 'array hook' => [new class
        {
            public function toArray(): array
            {
                return ['name' => 'Ada'];
            }
        }, 'name: Ada'];
        yield 'json hook precedes json serialization' => [new class implements \JsonSerializable
        {
            public function toJSON(): mixed
            {
                return (object) ['chosen' => true];
            }

            public function jsonSerialize(): mixed
            {
                return 'unused';
            }
        }, 'chosen: true'];
    }

    #[DataProvider('values')]
    public function test_php_values_are_normalized_before_41_encoding(mixed $value, string $expected): void
    {
        self::assertSame($expected, Toon::encode($value, new EncodeOptions(version: SpecVersion::V4_1)));
    }
}

enum Version41Unit
{
    case Ready;
}
