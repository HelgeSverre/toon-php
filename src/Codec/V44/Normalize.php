<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Codec\V44;

use DateTimeInterface;
use JsonSerializable;

/** @internal Version-specific codec implementation. */
final class Normalize
{
    /**
     * Normalize a PHP value into a form suitable for TOON encoding.
     *
     * Handles type conversion, object serialization, and recursive normalization
     * of nested structures. Converts non-finite floats to null, DateTime to ISO strings,
     * and objects via JsonSerializable, toArray(), or public properties.
     *
     * @param  mixed  $value  The value to normalize
     * @return mixed The normalized value (primitives, lists, or stdClass objects)
     */
    public static function normalizeValue(mixed $value): mixed
    {
        // Handle null
        if ($value === null) {
            return null;
        }

        // Handle primitives
        if (is_string($value)) {
            return self::checkUtf8($value);
        }
        if (is_bool($value)) {
            return $value;
        }

        // Handle numbers
        if (is_int($value) || is_float($value)) {
            // Convert non-finite values to null
            if (is_float($value) && ! is_finite($value)) {
                return null;
            }

            // Canonicalize -0.0 to 0
            if (is_float($value) && $value === 0.0 && fdiv(1, $value) === -INF) {
                return 0;
            }

            return $value;
        }

        // Handle DateTime objects -> ISO string
        if ($value instanceof DateTimeInterface) {
            return $value->format('c');
        }

        if ($value instanceof \BackedEnum) {
            return self::normalizeValue($value->value);
        }

        if ($value instanceof \UnitEnum) {
            return self::normalizeValue($value->name);
        }

        // Handle arrays
        if (is_array($value)) {
            // Check if it's a list (sequential integer keys starting at 0)
            if (array_is_list($value)) {
                return array_map(fn ($item) => self::normalizeValue($item), $value);
            }

            // It's an associative array, treat as object
            $result = [];
            foreach ($value as $key => $val) {
                $result[self::checkUtf8((string) $key)] = self::normalizeValue($val);
            }

            return (object) $result;
        }

        // Handle objects
        if (is_object($value)) {
            // Handle toJSON method first (highest priority for custom serialization)
            if (method_exists($value, 'toJSON')) {
                $serialized = $value->toJSON();

                // Guard against infinite recursion - if toJSON returns the same object, fall through
                if ($serialized !== $value) {
                    return self::normalizeValue($serialized);
                }
            }

            // Handle JsonSerializable (standard PHP interface)
            if ($value instanceof JsonSerializable) {
                return self::normalizeValue($value->jsonSerialize());
            }

            // Handle objects with toArray method
            if (method_exists($value, 'toArray')) {
                return self::normalizeValue($value->toArray());
            }

            // For stdClass and any other objects: use public properties only
            // This prevents leaking private/protected properties
            $result = [];
            foreach (get_object_vars($value) as $key => $val) {
                $result[self::checkUtf8((string) $key)] = self::normalizeValue($val);
            }

            return (object) $result;
        }

        // Fallback for unsupported types
        return null;
    }

    /**
     * Check if a value is a JSON primitive (string, number, boolean, or null).
     *
     * @param  mixed  $value  The value to check
     * @return bool True if the value is a primitive type
     */
    public static function isJsonPrimitive(mixed $value): bool
    {
        return is_string($value) || is_int($value) || is_float($value) || is_bool($value) || $value === null;
    }

    /**
     * Check if a value is a JSON array (PHP list with sequential integer keys).
     *
     * @param  mixed  $value  The value to check
     * @return bool True if the value is an array list
     *
     * @phpstan-assert-if-true array $value
     */
    public static function isJsonArray(mixed $value): bool
    {
        return is_array($value) && array_is_list($value);
    }

    /**
     * Check if a value is a JSON object (normalized stdClass).
     *
     * @param  mixed  $value  The value to check
     * @return bool True if the value is a normalized object
     *
     * @phpstan-assert-if-true \stdClass $value
     */
    public static function isJsonObject(mixed $value): bool
    {
        return $value instanceof \stdClass;
    }

    public static function isEmptyObject(mixed $value): bool
    {
        return $value instanceof \stdClass && get_object_vars($value) === [];
    }

    private static function checkUtf8(string $value): string
    {
        if (preg_match('//u', $value) !== 1) {
            throw new \InvalidArgumentException('Cannot encode a string that is not valid UTF-8');
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $value
     */
    public static function isArrayOfPrimitives(array $value): bool
    {
        if (! array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! self::isJsonPrimitive($item)) {
                return false;
            }
        }

        return true;
    }

    private function __construct()
    {
        // Prevent instantiation
    }
}
