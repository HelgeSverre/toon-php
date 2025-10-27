<?php

declare(strict_types=1);

namespace HelgeSverre\Toon;

use DateTimeInterface;
use JsonSerializable;
use ReflectionClass;
use stdClass;

final class Normalize
{
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
            // Canonicalize -0 to 0
            if ($value === 0 && is_float($value) && 1 / $value === -INF) {
                return 0;
            }

            // Convert non-finite values to null
            if (is_float($value) && ! is_finite($value)) {
                return null;
            }

            return $value;
        }

        // Handle DateTime objects -> ISO string
        if ($value instanceof DateTimeInterface) {
            return $value->format('c');
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
            // Handle stdClass and plain objects
            if ($value instanceof stdClass || self::isPlainObject($value)) {
                $result = [];
                foreach (get_object_vars($value) as $key => $val) {
                    $result[$key] = self::normalizeValue($val);
                }

                return $result;
            }

            // Handle objects with toArray method
            if (method_exists($value, 'toArray')) {
                return self::normalizeValue($value->toArray());
            }

            // Handle JsonSerializable
            if ($value instanceof JsonSerializable) {
                return self::normalizeValue($value->jsonSerialize());
            }

            // Fallback: convert to array
            return self::normalizeValue((array) $value);
        }

        // Fallback for unsupported types
        return null;
    }

    public static function isJsonPrimitive(mixed $value): bool
    {
        return is_string($value) || is_int($value) || is_float($value) || is_bool($value) || $value === null;
    }

    public static function isJsonArray(mixed $value): bool
    {
        return is_array($value) && array_is_list($value);
    }

    public static function isJsonObject(mixed $value): bool
    {
        return is_array($value) && ! array_is_list($value);
    }

    public static function isPlainObject(mixed $value): bool
    {
        if (! is_object($value)) {
            return false;
        }

        $class = get_class($value);

        // stdClass is always plain
        if ($class === 'stdClass') {
            return true;
        }

        // Anonymous classes are not plain
        if (str_contains($class, '@anonymous')) {
            return false;
        }

        // Built-in classes are not plain
        $reflection = new ReflectionClass($value);
        if ($reflection->isInternal()) {
            return false;
        }

        return false;
    }

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

    public static function isArrayOfArrays(array $value): bool
    {
        if (! array_is_list($value)) {
            return false;
        }

        if (empty($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_array($item) || ! array_is_list($item)) {
                return false;
            }
        }

        return true;
    }

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
