<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\SpecVersion;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Version44RoundTripTest extends TestCase
{
    public static function documents(): iterable
    {
        // A private deterministic generator keeps failures reproducible without
        // changing PHP's process-wide random state.
        $state = 44;
        for ($i = 0; $i < 200; $i++) {
            $value = self::value($state, 0);
            foreach ([',', '|', "\t"] as $delimiter) {
                foreach ([1, 2, 4] as $indent) {
                    yield $i.'/'.bin2hex($delimiter).'/'.$indent => [$value, $delimiter, $indent];
                }
            }
        }
    }

    #[DataProvider('documents')]
    public function test_generated_values_round_trip(mixed $value, string $delimiter, int $indent): void
    {
        $options = new EncodeOptions($indent, $delimiter, SpecVersion::V4_4);
        $wire = Toon::encode($value, $options);
        $decoded = Toon::decode($wire, new DecodeOptions($indent, true, SpecVersion::V4_4));
        self::assertModel($value, $decoded, $wire);
    }

    public static function floats(): iterable
    {
        $state = 2026;
        for ($i = 0; $i < 300; $i++) {
            $value = unpack('Evalue', pack('N2', self::next($state), self::next($state)))['value'];
            if (is_finite($value)) {
                yield (string) $i => [$value];
            }
        }
    }

    #[DataProvider('floats')]
    public function test_finite_binary64_values_round_trip_exactly(float $value): void
    {
        $wire = Toon::encode($value, new EncodeOptions(version: SpecVersion::V4_4));
        self::assertSame($value === 0.0 ? 0.0 : $value, (float) Toon::decode($wire, new DecodeOptions(version: SpecVersion::V4_4)), $wire);
    }

    private static function next(int &$state): int
    {
        $state = ($state * 1664525 + 1013904223) & 0xFFFFFFFF;

        return $state;
    }

    private static function value(int &$state, int $depth): mixed
    {
        $atoms = [null, false, true, 0, -1, 42, PHP_INT_MAX, PHP_INT_MIN, 1.25, 1.2345678901234567e-6, '', 'true', '+1', '.5', 'a,b|c', '[]', '#comment', "\u{FEFF}hello", "\0x", "a\nb", "a\tb", '😀', "\u{00a0}", 'a"b\\c', '0x10'];
        $kind = self::next($state) % 7;
        if ($depth >= 4 || $kind < 2) {
            return $atoms[self::next($state) % count($atoms)];
        }
        if ($kind === 2) {
            // Deliberately produce uniform tables and nested field groups.
            $rows = [];
            for ($i = 0; $i < 3; $i++) {
                $rows[] = (object) ['id' => $i, 'nested' => (object) ["\0name" => $atoms[self::next($state) % count($atoms)]]];
            }

            return $rows;
        }
        if ($kind === 3) {
            return (object) ['first' => (object) ['value' => self::value($state, $depth + 1)], 'second' => (object) ['value' => self::value($state, $depth + 1)]];
        }

        $items = [];
        $count = self::next($state) % 5;
        $keys = ['0', '01', 'a', 'a.b', "a\n", '', "\0x", 'é', 'z'];
        for ($i = 0; $i < $count; $i++) {
            $key = $kind === 4 ? $i : $keys[self::next($state) % count($keys)];
            $items[$key] = self::value($state, $depth + 1);
        }

        return $kind === 4 ? $items : (object) $items;
    }

    private static function assertModel(mixed $expected, mixed $actual, string $wire): void
    {
        if ($expected instanceof \stdClass) {
            $expected = get_object_vars($expected);
        }
        if (is_array($expected)) {
            self::assertIsArray($actual, $wire);
            self::assertCount(count($expected), $actual, $wire);
            foreach ($expected as $key => $value) {
                self::assertArrayHasKey($key, $actual, $wire);
                self::assertModel($value, $actual[$key], $wire);
            }
        } elseif (is_float($expected)) {
            self::assertTrue(is_int($actual) || is_float($actual), $wire);
            self::assertSame($expected, (float) $actual, $wire);
        } else {
            self::assertSame($expected, $actual, $wire);
        }
    }
}
