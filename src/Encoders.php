<?php

declare(strict_types=1);

namespace HelgeSverre\Toon;

final class Encoders
{
    public static function encodeValue(mixed $value, LineWriter $writer, EncodeOptions $options, int $depth = 0): void
    {
        // Handle primitives
        if (Normalize::isJsonPrimitive($value)) {
            $writer->push($depth, Primitives::encodePrimitive($value, $options->delimiter));

            return;
        }

        // Handle empty arrays - treat as empty objects at root level
        if (is_array($value) && empty($value)) {
            // Empty arrays at root are treated as empty objects (output nothing)
            return;
        }

        // Handle arrays
        if (Normalize::isJsonArray($value)) {
            self::encodeArray($value, $writer, $options, $depth);

            return;
        }

        // Handle objects
        if (Normalize::isJsonObject($value)) {
            self::encodeObject($value, $writer, $options, $depth);

            return;
        }

        // Fallback
        $writer->push($depth, Constants::NULL_LITERAL);
    }

    private static function encodeObject(array $object, LineWriter $writer, EncodeOptions $options, int $depth): void
    {
        foreach ($object as $key => $value) {
            self::encodeKeyValuePair((string) $key, $value, $writer, $options, $depth);
        }
    }

    private static function encodeKeyValuePair(string $key, mixed $value, LineWriter $writer, EncodeOptions $options, int $depth, bool $isListItem = false): void
    {
        // Encode the key (might need quoting) - pass isKey: true
        $encodedKey = Primitives::encodeStringLiteral($key, $options->delimiter, true);
        $prefix = $isListItem ? Constants::LIST_ITEM_PREFIX : '';

        // Handle primitives inline
        if (Normalize::isJsonPrimitive($value)) {
            $encodedValue = Primitives::encodePrimitive($value, $options->delimiter);
            $writer->push($depth, $prefix.$encodedKey.Constants::COLON.Constants::SPACE.$encodedValue);

            return;
        }

        // Handle arrays
        if (Normalize::isJsonArray($value)) {
            $array = $value;

            // Empty array - treat as empty object in PHP (since we can't distinguish)
            if (empty($array)) {
                $writer->push($depth, $prefix.$encodedKey.Constants::COLON);

                return;
            }

            // Inline primitive array
            if (Normalize::isArrayOfPrimitives($array)) {
                $inlineArray = self::formatInlineArray($array, $options);
                $writer->push($depth, $prefix.$encodedKey.$inlineArray);

                return;
            }

            // Array of arrays
            if (Normalize::isArrayOfArrays($array)) {
                $lengthPrefix = $options->lengthMarker !== false ? $options->lengthMarker : '';
                $delimiterKey = self::getDelimiterKey($options->delimiter);
                $writer->push($depth, $prefix.$encodedKey.Constants::OPEN_BRACKET.$lengthPrefix.count($array).$delimiterKey.Constants::CLOSE_BRACKET.Constants::COLON);
                foreach ($array as $item) {
                    $inlineArray = self::formatInlineArray($item, $options);
                    $writer->push($depth + 1, Constants::LIST_ITEM_PREFIX.$inlineArray);
                }

                return;
            }

            // Array of objects - try tabular format
            if (Normalize::isArrayOfObjects($array)) {
                $header = self::detectTabularHeader($array);
                if ($header !== null && self::isTabularArray($array, $header)) {
                    $writer->push($depth, $prefix.$encodedKey.self::formatArrayHeader(count($array), $header, $options));
                    self::writeTabularRows($array, $header, $writer, $options, $depth + 1);

                    return;
                }

                // Fall back to list format
                $writer->push($depth, $prefix.$encodedKey.Constants::OPEN_BRACKET.count($array).Constants::CLOSE_BRACKET.Constants::COLON);
                foreach ($array as $item) {
                    self::encodeObjectAsListItem($item, $writer, $options, $depth + 1);
                }

                return;
            }

            // Mixed array - use list format
            $writer->push($depth, $prefix.$encodedKey.Constants::OPEN_BRACKET.count($array).Constants::CLOSE_BRACKET.Constants::COLON);
            foreach ($array as $item) {
                self::encodeMixedArrayItem($item, $writer, $options, $depth + 1);
            }

            return;
        }

        // Handle nested objects
        if (Normalize::isJsonObject($value)) {
            $object = $value;

            // Empty object
            if (empty($object)) {
                $writer->push($depth, $prefix.$encodedKey.Constants::COLON);

                return;
            }

            // Non-empty object
            $writer->push($depth, $prefix.$encodedKey.Constants::COLON);
            self::encodeObject($object, $writer, $options, $depth + 1);

            return;
        }

        // Fallback
        $writer->push($depth, $prefix.$encodedKey.Constants::COLON.Constants::SPACE.Constants::NULL_LITERAL);
    }

    private static function encodeArray(array $array, LineWriter $writer, EncodeOptions $options, int $depth): void
    {
        // Empty array
        if (empty($array)) {
            $writer->push($depth, Constants::OPEN_BRACKET.'0'.Constants::CLOSE_BRACKET.Constants::COLON);

            return;
        }

        // Inline primitive array
        if (Normalize::isArrayOfPrimitives($array)) {
            $inlineArray = self::formatInlineArray($array, $options);
            $writer->push($depth, $inlineArray);

            return;
        }

        // Array of arrays
        if (Normalize::isArrayOfArrays($array)) {
            $lengthPrefix = $options->lengthMarker !== false ? $options->lengthMarker : '';
            $delimiterKey = self::getDelimiterKey($options->delimiter);
            $writer->push($depth, Constants::OPEN_BRACKET.$lengthPrefix.count($array).$delimiterKey.Constants::CLOSE_BRACKET.Constants::COLON);
            foreach ($array as $item) {
                $inlineArray = self::formatInlineArray($item, $options);
                $writer->push($depth + 1, Constants::LIST_ITEM_PREFIX.$inlineArray);
            }

            return;
        }

        // Array of objects - try tabular format
        if (Normalize::isArrayOfObjects($array)) {
            $header = self::detectTabularHeader($array);
            if ($header !== null && self::isTabularArray($array, $header)) {
                $writer->push($depth, self::formatArrayHeader(count($array), $header, $options));
                self::writeTabularRows($array, $header, $writer, $options, $depth + 1);

                return;
            }

            // Fall back to list format
            $writer->push($depth, Constants::OPEN_BRACKET.count($array).Constants::CLOSE_BRACKET.Constants::COLON);
            foreach ($array as $item) {
                self::encodeObjectAsListItem($item, $writer, $options, $depth + 1);
            }

            return;
        }

        // Mixed array - use list format
        $writer->push($depth, Constants::OPEN_BRACKET.count($array).Constants::CLOSE_BRACKET.Constants::COLON);
        foreach ($array as $item) {
            self::encodeMixedArrayItem($item, $writer, $options, $depth + 1);
        }
    }

    private static function formatInlineArray(array $array, EncodeOptions $options): string
    {
        $length = count($array);
        $lengthPrefix = $options->lengthMarker !== false ? $options->lengthMarker : '';
        $delimiterKey = self::getDelimiterKey($options->delimiter);

        $encoded = array_map(
            fn ($item) => Primitives::encodePrimitive($item, $options->delimiter),
            $array
        );

        $joined = implode($options->delimiter, $encoded);

        // Only add space after colon if there are items
        return Constants::OPEN_BRACKET.$lengthPrefix.$length.$delimiterKey.Constants::CLOSE_BRACKET.Constants::COLON.($joined !== '' ? Constants::SPACE.$joined : '');
    }

    private static function formatArrayHeader(int $length, array $fields, EncodeOptions $options): string
    {
        $lengthPrefix = $options->lengthMarker !== false ? $options->lengthMarker : '';
        $delimiterKey = self::getDelimiterKey($options->delimiter);

        // Quote field names if they contain special characters - these are keys
        $quotedFields = array_map(
            fn ($field) => Primitives::encodeStringLiteral($field, $options->delimiter, true),
            $fields
        );
        $fieldsList = implode($options->delimiter, $quotedFields);

        return Constants::OPEN_BRACKET.$lengthPrefix.$length.$delimiterKey.Constants::CLOSE_BRACKET.Constants::OPEN_BRACE.$fieldsList.Constants::CLOSE_BRACE.Constants::COLON;
    }

    private static function detectTabularHeader(?array $array): ?array
    {
        if ($array === null || empty($array)) {
            return null;
        }

        $firstObject = reset($array);
        if (! Normalize::isJsonObject($firstObject)) {
            return null;
        }

        return array_keys($firstObject);
    }

    private static function isTabularArray(array $array, array $expectedFields): bool
    {
        // Sort expected fields for comparison
        $sortedExpected = $expectedFields;
        sort($sortedExpected);

        foreach ($array as $item) {
            if (! Normalize::isJsonObject($item)) {
                return false;
            }

            $keys = array_keys($item);
            $sortedKeys = $keys;
            sort($sortedKeys);

            // Check if same set of keys (order doesn't matter)
            if ($sortedKeys !== $sortedExpected) {
                return false;
            }

            // All values must be primitives
            foreach ($item as $value) {
                if (! Normalize::isJsonPrimitive($value)) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function writeTabularRows(array $array, array $fields, LineWriter $writer, EncodeOptions $options, int $depth): void
    {
        foreach ($array as $object) {
            $values = [];
            foreach ($fields as $field) {
                $values[] = Primitives::encodePrimitive($object[$field], $options->delimiter);
            }
            $writer->push($depth, implode($options->delimiter, $values));
        }
    }

    private static function encodeObjectAsListItem(array $object, LineWriter $writer, EncodeOptions $options, int $depth): void
    {
        $keys = array_keys($object);
        if (empty($keys)) {
            $writer->push($depth, Constants::LIST_ITEM_PREFIX);

            return;
        }

        // First key-value pair always on the marker line
        $firstKey = $keys[0];
        self::encodeKeyValuePair($firstKey, $object[$firstKey], $writer, $options, $depth, true);

        // Remaining properties indented
        for ($i = 1; $i < count($keys); $i++) {
            $key = $keys[$i];
            $value = $object[$key];
            self::encodeKeyValuePair($key, $value, $writer, $options, $depth + 1);
        }
    }

    private static function encodeMixedArrayItem(mixed $item, LineWriter $writer, EncodeOptions $options, int $depth): void
    {
        // Primitives
        if (Normalize::isJsonPrimitive($item)) {
            $encoded = Primitives::encodePrimitive($item, $options->delimiter);
            $writer->push($depth, Constants::LIST_ITEM_PREFIX.$encoded);

            return;
        }

        // Arrays
        if (Normalize::isJsonArray($item)) {
            if (Normalize::isArrayOfPrimitives($item)) {
                $inlineArray = self::formatInlineArray($item, $options);
                $writer->push($depth, Constants::LIST_ITEM_PREFIX.$inlineArray);

                return;
            }

            // Complex array
            $writer->push($depth, Constants::LIST_ITEM_PREFIX.Constants::OPEN_BRACKET.count($item).Constants::CLOSE_BRACKET.Constants::COLON);
            foreach ($item as $subItem) {
                self::encodeMixedArrayItem($subItem, $writer, $options, $depth + 1);
            }

            return;
        }

        // Objects
        if (Normalize::isJsonObject($item)) {
            self::encodeObjectAsListItem($item, $writer, $options, $depth);

            return;
        }

        // Fallback
        $writer->push($depth, Constants::LIST_ITEM_PREFIX.Constants::NULL_LITERAL);
    }

    private static function getDelimiterKey(string $delimiter): string
    {
        return match ($delimiter) {
            Constants::DELIMITER_TAB => '\t',
            Constants::DELIMITER_PIPE => '|',
            default => '',
        };
    }

    private function __construct()
    {
        // Prevent instantiation
    }
}
