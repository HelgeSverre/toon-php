<?php

declare(strict_types=1);

namespace HelgeSverre\Toon;

final class Encoders
{
    public function __construct(
        private readonly EncodeOptions $options,
        private readonly LineWriter $writer
    ) {}

    /**
     * Encode any value to TOON format, writing output to a LineWriter.
     *
     * Handles primitives, arrays, and objects. Automatically selects the most
     * compact representation (inline arrays, tabular format, or list format).
     *
     * @param  mixed  $value  The value to encode
     * @param  int  $depth  Current indentation depth (default: 0)
     */
    public function encodeValue(mixed $value, int $depth = 0): void
    {
        // Handle primitives
        if (Normalize::isJsonPrimitive($value)) {
            $this->writer->push($depth, Primitives::encodePrimitive($value, $this->options->delimiter));

            return;
        }

        // Handle empty arrays - treat as empty objects at root level
        if (is_array($value) && empty($value)) {
            // Empty arrays at root are treated as empty objects (output nothing)
            return;
        }

        // Handle arrays
        if (Normalize::isJsonArray($value)) {
            $this->encodeArray($value, $depth);

            return;
        }

        // Handle objects
        if (Normalize::isJsonObject($value)) { // @phpstan-ignore staticMethod.impossibleType
            $this->encodeObject($value, $depth);

            return;
        }

        // Fallback
        $this->writer->push($depth, Constants::NULL_LITERAL);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function encodeObject(array $object, int $depth): void
    {
        foreach ($object as $key => $value) {
            $this->encodeKeyValuePair((string) $key, $value, $depth);
        }
    }

    private function encodeKeyValuePair(string $key, mixed $value, int $depth, bool $isListItem = false): void
    {
        // Encode the key using identifier pattern matching
        $encodedKey = Primitives::encodeKey($key);
        $prefix = $isListItem ? Constants::LIST_ITEM_PREFIX : '';

        // Handle primitives inline
        if (Normalize::isJsonPrimitive($value)) {
            $encodedValue = Primitives::encodePrimitive($value, $this->options->delimiter);
            $this->writer->push($depth, $prefix.$encodedKey.Constants::COLON.Constants::SPACE.$encodedValue);

            return;
        }

        // Handle arrays
        if (Normalize::isJsonArray($value)) {
            $array = $value;

            // Empty array
            if (empty($array)) {
                $inlineArray = $this->formatInlineArray($array, $key);
                $this->writer->push($depth, $prefix.$inlineArray);

                return;
            }

            // Inline primitive array
            if (Normalize::isArrayOfPrimitives($array)) {
                $inlineArray = $this->formatInlineArray($array, $key);
                $this->writer->push($depth, $prefix.$inlineArray);

                return;
            }

            // Array of arrays
            if (Normalize::isArrayOfArrays($array)) {
                $delimiterKey = $this->getDelimiterKey($this->options->delimiter);
                $this->writer->push($depth, $prefix.$encodedKey.Constants::OPEN_BRACKET.count($array).$delimiterKey.Constants::CLOSE_BRACKET.Constants::COLON);
                foreach ($array as $item) {
                    $inlineArray = $this->formatInlineArray($item);
                    $this->writer->push($depth + 1, Constants::LIST_ITEM_PREFIX.$inlineArray);
                }

                return;
            }

            // Array of objects - try tabular format
            if (Normalize::isArrayOfObjects($array)) {
                $header = $this->detectTabularHeader($array);
                if ($header !== null && $this->isTabularArray($array, $header)) {
                    $this->writer->push($depth, $prefix.$this->formatArrayHeader(count($array), $header, $key));
                    $this->writeTabularRows($array, $header, $depth + 1);

                    return;
                }

                // Fall back to list format
                $this->writer->push($depth, $prefix.$encodedKey.Constants::OPEN_BRACKET.count($array).Constants::CLOSE_BRACKET.Constants::COLON);
                foreach ($array as $item) {
                    $this->encodeObjectAsListItem($item, $depth + 1);
                }

                return;
            }

            // Mixed array - use list format
            $this->writer->push($depth, $prefix.$encodedKey.Constants::OPEN_BRACKET.count($array).Constants::CLOSE_BRACKET.Constants::COLON);
            foreach ($array as $item) {
                $this->encodeMixedArrayItem($item, $depth + 1);
            }

            return;
        }

        // Handle nested objects
        if (Normalize::isJsonObject($value)) { // @phpstan-ignore staticMethod.impossibleType
            $object = $value;

            // Empty object
            if (empty($object)) { // @phpstan-ignore empty.variable

                $this->writer->push($depth, $prefix.$encodedKey.Constants::COLON);

                return;
            }

            // Non-empty object
            $this->writer->push($depth, $prefix.$encodedKey.Constants::COLON);
            $this->encodeObject($object, $depth + 1);

            return;
        }

        // Fallback
        $this->writer->push($depth, $prefix.$encodedKey.Constants::COLON.Constants::SPACE.Constants::NULL_LITERAL);
    }

    /**
     * @param  array<mixed>  $array
     */
    private function encodeArray(array $array, int $depth): void
    {
        // Note: Empty arrays are handled in encodeValue() before this method is called

        // Inline primitive array
        if (Normalize::isArrayOfPrimitives($array)) {
            $inlineArray = $this->formatInlineArray($array);
            $this->writer->push($depth, $inlineArray);

            return;
        }

        // Array of arrays
        if (Normalize::isArrayOfArrays($array)) {
            $delimiterKey = $this->getDelimiterKey($this->options->delimiter);
            $this->writer->push($depth, Constants::OPEN_BRACKET.count($array).$delimiterKey.Constants::CLOSE_BRACKET.Constants::COLON);
            foreach ($array as $item) {
                $inlineArray = $this->formatInlineArray($item); // @phpstan-ignore argument.type
                $this->writer->push($depth + 1, Constants::LIST_ITEM_PREFIX.$inlineArray);
            }

            return;
        }

        // Array of objects - try tabular format
        if (Normalize::isArrayOfObjects($array)) {
            $header = $this->detectTabularHeader($array);
            if ($header !== null && $this->isTabularArray($array, $header)) {
                $this->writer->push($depth, $this->formatArrayHeader(count($array), $header, null));
                $this->writeTabularRows($array, $header, $depth + 1);

                return;
            }

            // Fall back to list format
            $this->writer->push($depth, Constants::OPEN_BRACKET.count($array).Constants::CLOSE_BRACKET.Constants::COLON);
            foreach ($array as $item) {
                $this->encodeObjectAsListItem($item, $depth + 1); // @phpstan-ignore argument.type
            }

            return;
        }

        // Mixed array - use list format
        $this->writer->push($depth, Constants::OPEN_BRACKET.count($array).Constants::CLOSE_BRACKET.Constants::COLON);
        foreach ($array as $item) {
            $this->encodeMixedArrayItem($item, $depth + 1);
        }
    }

    /**
     * @param  array<mixed>  $array
     * @param  string|null  $key  Optional key for the array (for header quoting)
     */
    private function formatInlineArray(array $array, ?string $key = null): string
    {
        $length = count($array);
        $delimiterKey = $this->getDelimiterKey($this->options->delimiter);

        $encoded = array_map(
            fn ($item) => Primitives::encodePrimitive($item, $this->options->delimiter),
            $array
        );

        $joined = implode($this->options->delimiter, $encoded);

        // Build header with optional key prefix
        $header = '';
        if ($key !== null) {
            $header = Primitives::encodeKey($key);
        }

        // Only add space after colon if there are items
        return $header.Constants::OPEN_BRACKET.$length.$delimiterKey.Constants::CLOSE_BRACKET.Constants::COLON.($joined !== '' ? Constants::SPACE.$joined : '');
    }

    /**
     * Format a tabular array header with field declarations.
     *
     * Field names are encoded as keys following the same quoting rules (§7.3).
     *
     * @param  array<string>  $fields
     * @param  string|null  $key  Optional key for the array
     */
    private function formatArrayHeader(int $length, array $fields, ?string $key = null): string
    {
        $delimiterKey = $this->getDelimiterKey($this->options->delimiter);

        // Encode field names as keys
        $quotedFields = array_map(
            fn ($field) => Primitives::encodeKey($field),
            $fields
        );
        $fieldsList = implode($this->options->delimiter, $quotedFields);

        // Build header with optional key prefix
        $header = '';
        if ($key !== null) {
            $header = Primitives::encodeKey($key);
        }

        return $header.Constants::OPEN_BRACKET.$length.$delimiterKey.Constants::CLOSE_BRACKET.Constants::OPEN_BRACE.$fieldsList.Constants::CLOSE_BRACE.Constants::COLON;
    }

    /**
     * @param  array<mixed>|null  $array
     * @return array<string>|null
     */
    private function detectTabularHeader(?array $array): ?array
    {
        if ($array === null || empty($array)) {
            return null;
        }

        $firstKey = array_key_first($array);
        $firstObject = $array[$firstKey];
        if (! Normalize::isJsonObject($firstObject)) {
            return null;
        }

        return array_keys($firstObject);
    }

    /**
     * @param  array<mixed>  $array
     * @param  array<string>  $expectedFields
     */
    private function isTabularArray(array $array, array $expectedFields): bool
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

    /**
     * @param  array<mixed>  $array
     * @param  array<string>  $fields
     */
    private function writeTabularRows(array $array, array $fields, int $depth): void
    {
        foreach ($array as $object) {
            $values = [];
            foreach ($fields as $field) {
                $values[] = Primitives::encodePrimitive($object[$field], $this->options->delimiter); // @phpstan-ignore offsetAccess.nonOffsetAccessible
            }
            $this->writer->push($depth, implode($this->options->delimiter, $values));
        }
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function encodeObjectAsListItem(array $object, int $depth): void
    {
        $keys = array_keys($object);
        if (empty($keys)) {
            $this->writer->push($depth, Constants::LIST_ITEM_PREFIX);

            return;
        }

        // First key-value pair always on the marker line
        $firstKey = $keys[0];
        $this->encodeKeyValuePair($firstKey, $object[$firstKey], $depth, true);

        // Remaining properties indented
        for ($i = 1; $i < count($keys); $i++) {
            $key = $keys[$i];
            $value = $object[$key];
            $this->encodeKeyValuePair($key, $value, $depth + 1);
        }
    }

    private function encodeMixedArrayItem(mixed $item, int $depth): void
    {
        // Primitives
        if (Normalize::isJsonPrimitive($item)) {
            $encoded = Primitives::encodePrimitive($item, $this->options->delimiter);
            $this->writer->push($depth, Constants::LIST_ITEM_PREFIX.$encoded);

            return;
        }

        // Arrays
        if (Normalize::isJsonArray($item)) {
            if (Normalize::isArrayOfPrimitives($item)) {
                $inlineArray = $this->formatInlineArray($item);
                $this->writer->push($depth, Constants::LIST_ITEM_PREFIX.$inlineArray);

                return;
            }

            // Complex array
            $this->writer->push($depth, Constants::LIST_ITEM_PREFIX.Constants::OPEN_BRACKET.count($item).Constants::CLOSE_BRACKET.Constants::COLON);
            foreach ($item as $subItem) {
                $this->encodeMixedArrayItem($subItem, $depth + 1);
            }

            return;
        }

        // Objects
        if (Normalize::isJsonObject($item)) { // @phpstan-ignore staticMethod.impossibleType
            $this->encodeObjectAsListItem($item, $depth);

            return;
        }

        // Fallback
        $this->writer->push($depth, Constants::LIST_ITEM_PREFIX.Constants::NULL_LITERAL);
    }

    private function getDelimiterKey(string $delimiter): string
    {
        return match ($delimiter) {
            Constants::DELIMITER_TAB => "\t",
            Constants::DELIMITER_PIPE => '|',
            default => '',
        };
    }
}
