<?php

declare(strict_types=1);

namespace HelgeSverre\Toon;

use DateTimeInterface;
use JsonSerializable;

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
     * @return mixed The normalized value (primitives, arrays, or associative arrays)
     */
    public static function normalizeValue(mixed $value): mixed
    {
        // Handle null
        if ($value === null) {
            return null;
        }

        // Handle primitives
        if (is_string($value) || is_bool($value)) {
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
                $result[(string) $key] = self::normalizeValue($val);
            }

            return $result;
        }

        // Handle objects
        if (is_object($value)) {
            // Handle JsonSerializable first (preferred method)
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
                $result[$key] = self::normalizeValue($val);
            }

            return $result;
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
     * Check if a value is a JSON object (PHP associative array).
     *
     * @param  mixed  $value  The value to check
     * @return bool True if the value is an associative array
     *
     * @phpstan-assert-if-true array $value
     */
    public static function isJsonObject(mixed $value): bool
    {
        return is_array($value) && ! array_is_list($value);
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

    /**
     * @param  array<mixed>  $value
     */
    public static function isArrayOfArrays(array $value): bool
    {
        if (! array_is_list($value)) {
            return false;
        }

        if (empty($value)) {
            return false;
        }

        foreach ($value as $item) {
            // Each item must be an array and specifically an array of primitives
            if (! is_array($item) || ! self::isArrayOfPrimitives($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<mixed>  $value
     */
    public static function isArrayOfObjects(array $value): bool
    {
        if (! array_is_list($value)) {
            return false;
        }

        if (empty($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! self::isJsonObject($item)) {
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
