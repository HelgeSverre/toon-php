<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\Exceptions\DecodeException;
use HelgeSverre\Toon\SpecVersion;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Throwable;

final class VersionedFixturesTest extends TestCase
{
    public static function fixtures(): iterable
    {
        foreach ([SpecVersion::V4_1, SpecVersion::V4_4] as $version) {
            foreach (json_decode(file_get_contents(__DIR__.'/Fixtures/v41-regressions.json'), false, 512, JSON_THROW_ON_ERROR) as $case) {
                yield $version->value.'/regression/'.$case->id => [$version, $case->op, $case->id, $case];
            }
        }
        foreach (SpecVersion::cases() as $version) {
            foreach (['encode', 'decode'] as $operation) {
                $files = glob(__DIR__.'/Fixtures/spec/'.$version->value.'/'.$operation.'/*.json');
                if ($files === false || $files === []) {
                    throw new \LogicException('Missing fixtures for '.$version->value.'/'.$operation);
                }
                foreach ($files as $file) {
                    $fixture = json_decode(file_get_contents($file), false, 512, JSON_THROW_ON_ERROR);
                    foreach ($fixture->tests as $index => $case) {
                        $id = $operation.'/'.basename($file).'#'.$index;
                        yield $version->value.'/'.$id.' '.$case->name => [$version, $operation, $id, $case];
                    }
                }
            }
        }
    }

    #[DataProvider('fixtures')]
    public function test_versioned_fixtures(SpecVersion $version, string $operation, string $id, stdClass $case): void
    {
        $options = $case->options ?? new stdClass;
        $encode = new EncodeOptions(indent: $options->indent ?? $options->indentSize ?? 2, delimiter: $options->delimiter ?? ',', version: $version);
        $decode = new DecodeOptions(indent: $options->indent ?? $options->indentSize ?? 2, strict: $options->strict ?? true, version: $version);
        $run = static function (?SpecVersion $selected) use ($operation, $case, $encode, $decode): array {
            try {
                return ['value' => $operation === 'encode'
                    ? Toon::encode($case->input, $selected === null ? new EncodeOptions($encode->indent, $encode->delimiter) : $encode)
                    : Toon::decode($case->input, $selected === null ? new DecodeOptions($decode->indent, $decode->strict) : $decode)];
            } catch (Throwable $error) {
                return ['error' => $error::class, 'message' => $error->getMessage()];
            }
        };
        $actual = $run($version);
        if ($version === SpecVersion::V3_3) {
            // Preserve the released behavior, including unsupported optional folding/expansion.
            $baseline = json_decode(file_get_contents(__DIR__.'/Fixtures/legacy-3.2.1.json'), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame($baseline['cases'][$id], $actual);
            self::assertSame($actual, $run(null), 'Omitting the version must preserve released behavior');

            $deviations = json_decode(file_get_contents(__DIR__.'/Fixtures/legacy-deviations.json'), true, 512, JSON_THROW_ON_ERROR);
            if (in_array($id, $deviations, true)) {
                return;
            }
        }
        if ($case->shouldError ?? false) {
            self::assertArrayHasKey('error', $actual);
            self::assertTrue(is_a($actual['error'], DecodeException::class, true) || $actual['error'] === \InvalidArgumentException::class, $actual['error']);

            return;
        }
        self::assertArrayHasKey('value', $actual, json_encode($actual));
        self::assertJsonValue($case->expected, $actual['value']);
        if ($version !== SpecVersion::V3_3 && $operation === 'encode') {
            self::assertJsonValue($case->input, Toon::decode($actual['value'], $decode), false);
            self::assertJsonValue($case->input, Toon::decode($actual['value'], $decode->withStrict(false)), false);
        }
    }

    private static function assertJsonValue(mixed $expected, mixed $actual, bool $checkOrder = true): void
    {
        // Decoding deliberately retains this PHP library's associative-array API.
        if ($expected instanceof stdClass) {
            $expected = (array) $expected;
        }
        if (is_array($expected)) {
            self::assertIsArray($actual);
            self::assertCount(count($expected), $actual);
            if ($checkOrder) {
                self::assertSame(array_keys($expected), array_keys($actual));
            }
            foreach ($expected as $key => $value) {
                self::assertArrayHasKey($key, $actual);
                self::assertJsonValue($value, $actual[$key], $checkOrder);
            }
        } elseif ((is_float($expected) || is_int($expected)) && (is_float($actual) || is_int($actual))) {
            if (is_int($expected) && is_int($actual)) {
                self::assertSame($expected, $actual);
            } else {
                self::assertSame((float) $expected, (float) $actual);
            }
        } else {
            self::assertSame($expected, $actual);
        }
    }
}
