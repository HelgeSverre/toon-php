<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use DateTime;
use HelgeSverre\Toon\Normalize;
use HelgeSverre\Toon\Toon;
use JsonSerializable;
use PHPUnit\Framework\TestCase;
use stdClass;

final class NormalizationTest extends TestCase
{
    public function test_normalize_json_serializable_priority(): void
    {
        $obj = new class implements JsonSerializable
        {
            private int $privateValue = 42;

            public int $publicValue = 99;

            public function jsonSerialize(): mixed
            {
                return ['custom' => 'serialization', 'value' => $this->privateValue];
            }

            /**
             * @return array<string, mixed>
             */
            public function toArray(): array
            {
                return ['from' => 'toArray', 'value' => $this->publicValue];
            }
        };

        // JsonSerializable should take priority over toArray
        $expected = "custom: serialization\nvalue: 42";
        $this->assertEquals($expected, Toon::encode($obj));
    }

    public function test_normalize_to_array_when_no_json_serializable(): void
    {
        $obj = new class
        {
            public int $publicValue = 99;

            /**
             * @return array<string, mixed>
             */
            public function toArray(): array
            {
                return ['from' => 'toArray', 'value' => $this->publicValue];
            }
        };

        // toArray should be used when JsonSerializable is not implemented
        $expected = "from: toArray\nvalue: 99";
        $this->assertEquals($expected, Toon::encode($obj));
    }

    public function test_normalize_public_properties_only(): void
    {
        $obj = new class
        {
            public string $publicProp = 'public';
        };

        // Only public properties should be exposed
        $expected = 'publicProp: public';
        $this->assertEquals($expected, Toon::encode($obj));
    }

    public function test_normalize_stdclass(): void
    {
        $obj = new stdClass;
        $obj->name = 'Ada';
        $obj->age = 30;

        $expected = "name: Ada\nage: 30";
        $this->assertEquals($expected, Toon::encode($obj));
    }

    public function test_normalize_negative_zero_float(): void
    {
        // Test that -0.0 is normalized to 0
        $this->assertEquals('0', Toon::encode(-0.0));
        $this->assertEquals('value: 0', Toon::encode(['value' => -0.0]));
    }

    public function test_normalize_large_floats_no_scientific_notation(): void
    {
        // Very large numbers (>10^20) should be quoted per §2.9 for exact precision
        $this->assertEquals('"1000000000000000000000"', Toon::encode(1e21));
        // Numbers at exactly 10^20 are still within range, not quoted
        $this->assertEquals('100000000000000000000', Toon::encode(1e20));
    }

    public function test_normalize_small_floats_no_scientific_notation(): void
    {
        // Small numbers should expand scientific notation
        $this->assertEquals('0.000001', Toon::encode(1e-6));
        $this->assertEquals('0.0000001', Toon::encode(1e-7));
    }

    public function test_normalize_locale_independent_floats(): void
    {
        // Floats should always use dot as decimal separator regardless of locale
        $this->assertEquals('3.14', Toon::encode(3.14));
        $this->assertEquals('0.5', Toon::encode(0.5));
        $this->assertEquals('-2.718', Toon::encode(-2.718));
    }

    public function test_normalize_value_handles_datetime_interface(): void
    {
        $date = new DateTime('2024-01-15 10:30:00', new \DateTimeZone('UTC'));
        $normalized = Normalize::normalizeValue($date);

        // Should be ISO 8601 format
        $this->assertIsString($normalized);
        $this->assertStringContainsString('2024-01-15', $normalized);
    }

    public function test_normalize_value_handles_non_finite_floats(): void
    {
        $this->assertNull(Normalize::normalizeValue(INF));
        $this->assertNull(Normalize::normalizeValue(-INF));
        $this->assertNull(Normalize::normalizeValue(NAN));
    }

    public function test_normalize_value_handles_negative_zero(): void
    {
        $normalized = Normalize::normalizeValue(-0.0);
        $this->assertSame(0, $normalized);
    }

    public function test_normalize_value_handles_associative_arrays(): void
    {
        $input = ['key' => 'value', 'number' => 42];
        $normalized = Normalize::normalizeValue($input);

        $this->assertIsArray($normalized);
        $this->assertEquals(['key' => 'value', 'number' => 42], $normalized);
    }

    public function test_normalize_value_handles_nested_structures(): void
    {
        $input = [
            'level1' => [
                'level2' => [
                    'value' => 123,
                ],
            ],
        ];

        $normalized = Normalize::normalizeValue($input);
        $this->assertEquals($input, $normalized);
    }

    public function test_normalize_value_converts_object_keys_to_strings(): void
    {
        $input = [0 => 'zero', 1 => 'one', 'key' => 'value'];
        // This is a list, so it should be treated as array
        $normalized = Normalize::normalizeValue(['key' => 'value', 123 => 'number']);

        $this->assertIsArray($normalized);
        $this->assertArrayHasKey('key', $normalized);
        $this->assertArrayHasKey('123', $normalized); // numeric keys converted to strings
    }

    public function test_is_array_of_primitives_returns_true_for_primitive_array(): void
    {
        $this->assertTrue(Normalize::isArrayOfPrimitives([1, 2, 3]));
        $this->assertTrue(Normalize::isArrayOfPrimitives(['a', 'b', 'c']));
        $this->assertTrue(Normalize::isArrayOfPrimitives([true, false, null]));
    }

    public function test_is_array_of_primitives_returns_false_for_mixed_array(): void
    {
        $this->assertFalse(Normalize::isArrayOfPrimitives([1, 2, [3, 4]]));
        $this->assertFalse(Normalize::isArrayOfPrimitives(['a', 'b', new stdClass]));
    }

    public function test_is_array_of_primitives_returns_false_for_associative_array(): void
    {
        $this->assertFalse(Normalize::isArrayOfPrimitives(['key' => 'value']));
    }

    public function test_is_array_of_arrays_returns_true_for_array_of_primitive_arrays(): void
    {
        $this->assertTrue(Normalize::isArrayOfArrays([[1, 2], [3, 4]]));
        $this->assertTrue(Normalize::isArrayOfArrays([['a', 'b'], ['c', 'd']]));
    }

    public function test_is_array_of_arrays_returns_false_for_empty_array(): void
    {
        $this->assertFalse(Normalize::isArrayOfArrays([]));
    }

    public function test_is_array_of_arrays_returns_false_when_contains_objects(): void
    {
        $this->assertFalse(Normalize::isArrayOfArrays([[1, 2], new stdClass]));
        $this->assertFalse(Normalize::isArrayOfArrays([[1, 2], ['key' => 'value']]));
    }

    public function test_is_array_of_objects_returns_true_for_object_array(): void
    {
        $this->assertTrue(Normalize::isArrayOfObjects([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]));
    }

    public function test_is_array_of_objects_returns_false_for_empty_array(): void
    {
        $this->assertFalse(Normalize::isArrayOfObjects([]));
    }

    public function test_is_array_of_objects_returns_false_when_contains_primitives(): void
    {
        $this->assertFalse(Normalize::isArrayOfObjects([
            ['id' => 1],
            'not an object',
        ]));
    }

    public function test_is_json_primitive(): void
    {
        $this->assertTrue(Normalize::isJsonPrimitive(null));
        $this->assertTrue(Normalize::isJsonPrimitive(true));
        $this->assertTrue(Normalize::isJsonPrimitive(false));
        $this->assertTrue(Normalize::isJsonPrimitive(42));
        $this->assertTrue(Normalize::isJsonPrimitive(3.14));
        $this->assertTrue(Normalize::isJsonPrimitive('string'));

        $this->assertFalse(Normalize::isJsonPrimitive([]));
        $this->assertFalse(Normalize::isJsonPrimitive(new stdClass));
    }

    public function test_is_json_array(): void
    {
        $this->assertTrue(Normalize::isJsonArray([1, 2, 3]));
        $this->assertTrue(Normalize::isJsonArray([]));

        $this->assertFalse(Normalize::isJsonArray(['key' => 'value']));
        $this->assertFalse(Normalize::isJsonArray('not an array')); // @phpstan-ignore staticMethod.impossibleType
    }

    public function test_is_json_object(): void
    {
        $this->assertTrue(Normalize::isJsonObject(['key' => 'value']));
        $this->assertTrue(Normalize::isJsonObject(['id' => 1, 'name' => 'Alice']));

        $this->assertFalse(Normalize::isJsonObject([1, 2, 3]));
        $this->assertFalse(Normalize::isJsonObject([])); // Empty array is a list
        $this->assertFalse(Normalize::isJsonObject(new stdClass)); // @phpstan-ignore staticMethod.impossibleType
    }

    public function test_normalize_enum(): void
    {
        /**
         * BackedEnums
         */
        $expected = 'active';
        $this->assertEquals($expected, Toon::encode(Status::ACTIVE));

        $expected = '201';
        $this->assertEquals($expected, Toon::encode(HttpCode::CREATED));

        /**
         * should return list of values
         */
        $expected = '[2]: 201,400';
        $this->assertEquals($expected, Toon::encode(HttpCode::cases()));

        /**
         * UnitEnums
         */
        $expected = 'TWO';
        $this->assertEquals($expected, Toon::encode(Counting::TWO));

        $expected = '[3]: ONE,TWO,THREE';
        $this->assertEquals($expected, Toon::encode(Counting::cases()));
    }

    public function test_normalize_object_with_enums(): void
    {
        $obj = new class
        {
            public Status $status = Status::INACTIVE;

            public Counting $count = Counting::THREE;
        };

        $expected = "status: inactive\ncount: THREE";
        $this->assertEquals($expected, Toon::encode($obj));
    }

    public function test_normalize_datetime_to_iso8601_exact(): void
    {
        // P1 High Priority: Test exact ISO 8601 format with timezone
        // PHP's DateTime->format('c') produces ISO 8601 format
        $date = new DateTime('2025-01-01T00:00:00.000Z', new \DateTimeZone('UTC'));
        $result = Toon::encode($date);

        // DateTime should be formatted as ISO 8601 string (quoted)
        $this->assertIsString($result);
        $this->assertStringStartsWith('"', $result);
        $this->assertStringEndsWith('"', $result);
        $this->assertStringContainsString('2025-01-01T00:00:00', $result);

        // Should include timezone information
        $this->assertMatchesRegularExpression('/"\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}"/', $result);
    }

    public function test_normalize_datetime_in_object_context(): void
    {
        // P1 High Priority: Test DateTime normalization within an object
        $date = new DateTime('2025-01-01T12:30:45', new \DateTimeZone('UTC'));
        $input = ['created' => $date, 'id' => 123];
        $result = Toon::encode($input);

        // Should contain the date in ISO format
        $this->assertStringContainsString('created:', $result);
        $this->assertStringContainsString('2025-01-01', $result);
        $this->assertStringContainsString('id: 123', $result);
    }
}

enum Counting
{
    case ONE;
    case TWO;
    case THREE;
}

enum Status: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

enum HttpCode: int
{
    case CREATED = 201;
    case ERROR = 400;
}
