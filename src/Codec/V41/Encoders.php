<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Codec\V41;

use HelgeSverre\Toon\Constants;
use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\LineWriter;

/** @internal Version-specific codec implementation. */
final class Encoders
{
    public function __construct(
        private readonly EncodeOptions $options,
        private readonly LineWriter $writer
    ) {}

    /**
     * Encode any value to TOON format, writing output to a LineWriter.
     *
     * The form follows from the value's shape and position, never from preference
     * (§1.4, §9): inline arrays, tabular arrays with nested field groups, keyed
     * tabular objects, and list form where no tabular form applies.
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

        // Handle arrays
        if (Normalize::isJsonArray($value)) {
            // §9.1: an empty array at the root is the literal "[]" on its own line.
            if ($value === []) {
                $this->writer->push($depth, Constants::EMPTY_ARRAY);

                return;
            }

            $this->encodeArray($value, $depth);

            return;
        }

        // Handle objects
        if (Normalize::isJsonObject($value)) {
            // §9.5: a keyed-eligible root object collapses into a keyless keyed header.
            $keyed = self::detectKeyedTabular(get_object_vars($value));
            if ($keyed !== null) {
                $this->writer->push($depth, $this->formatKeyedHeader(count(get_object_vars($value)), $keyed, null));
                $this->writeEntryRows(get_object_vars($value), $keyed, $depth + 1);

                return;
            }

            $this->encodeObject(get_object_vars($value), $depth);

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

        // §10 depth model: a first field carried on a hyphen line at depth d stands
        // at depth d+1, so a scope it opens has its content at depth d+2.
        $childDepth = $isListItem ? $depth + 2 : $depth + 1;

        // Handle primitives inline
        if (Normalize::isJsonPrimitive($value)) {
            $encodedValue = Primitives::encodePrimitive($value, $this->options->delimiter);
            $this->writer->push($depth, $prefix.$encodedKey.Constants::COLON.Constants::SPACE.$encodedValue);

            return;
        }

        // Handle arrays
        if (Normalize::isJsonArray($value)) {
            $array = $value;

            // §9.1: empty arrays in object-field position are "key: []".
            if ($array === []) {
                $this->writer->push($depth, $prefix.$encodedKey.Constants::COLON.Constants::SPACE.Constants::EMPTY_ARRAY);

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
                $this->writer->push($depth, $prefix.$this->formatListHeader(count($array), $key));
                foreach ($array as $item) {
                    $this->writer->push($childDepth, Constants::LIST_ITEM_PREFIX.$this->formatInlineArray($item));
                }

                return;
            }

            // Array of objects - tabular form is mandatory where detection succeeds (§9.3)
            $fields = Fields::detect($array);
            if ($fields !== null) {
                $this->writer->push($depth, $prefix.$this->formatArrayHeader(count($array), $fields, $key));
                $this->writeTabularRows($array, $fields, $childDepth);

                return;
            }

            // Otherwise list form (§9.4)
            $this->writer->push($depth, $prefix.$this->formatListHeader(count($array), $key));
            foreach ($array as $item) {
                $this->encodeMixedArrayItem($item, $childDepth);
            }

            return;
        }

        // Handle nested objects
        if (Normalize::isJsonObject($value)) {
            $object = get_object_vars($value);

            // §9.5: keyed tabular form applies in object-field position.
            $keyed = self::detectKeyedTabular($object);
            if ($keyed !== null) {
                $this->writer->push($depth, $prefix.$this->formatKeyedHeader(count($object), $keyed, $key));
                $this->writeEntryRows($object, $keyed, $childDepth);

                return;
            }

            // Empty object
            if (empty($object)) {
                $this->writer->push($depth, $prefix.$encodedKey.Constants::COLON);

                return;
            }

            // Non-empty object
            $this->writer->push($depth, $prefix.$encodedKey.Constants::COLON);
            $this->encodeObject($object, $childDepth);

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
            $this->writer->push($depth, $this->formatInlineArray($array));

            return;
        }

        // Array of arrays
        if (Normalize::isArrayOfArrays($array)) {
            $this->writer->push($depth, $this->formatListHeader(count($array), null));
            foreach ($array as $item) {
                $this->writer->push($depth + 1, Constants::LIST_ITEM_PREFIX.$this->formatInlineArray($item)); // @phpstan-ignore argument.type
            }

            return;
        }

        // Array of objects - tabular form is mandatory where detection succeeds (§9.3)
        $fields = Fields::detect($array);
        if ($fields !== null) {
            $this->writer->push($depth, $this->formatArrayHeader(count($array), $fields, null));
            $this->writeTabularRows($array, $fields, $depth + 1);

            return;
        }

        // Otherwise list form (§9.4)
        $this->writer->push($depth, $this->formatListHeader(count($array), null));
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
     * Format a header for an array in list form: `key[N<delim?>]:` (§9.2, §9.4).
     *
     * Every header declares the document delimiter as its active delimiter (§11.1).
     */
    private function formatListHeader(int $length, ?string $key): string
    {
        $header = $key !== null ? Primitives::encodeKey($key) : '';

        return $header
            .Constants::OPEN_BRACKET.$length.$this->getDelimiterKey($this->options->delimiter).Constants::CLOSE_BRACKET
            .Constants::COLON;
    }

    /**
     * Format a tabular array header with its field list (§9.3).
     *
     * Field names are encoded as keys following the same quoting rules (§7.3), and a
     * nested-uniform column carries its own nested field group.
     *
     * @param  array<int, array{name: string, children: array<int, mixed>|null}>  $fields
     * @param  string|null  $key  Optional key for the array
     */
    private function formatArrayHeader(int $length, array $fields, ?string $key = null): string
    {
        $header = $key !== null ? Primitives::encodeKey($key) : '';

        return $header
            .Constants::OPEN_BRACKET.$length.$this->getDelimiterKey($this->options->delimiter).Constants::CLOSE_BRACKET
            .Constants::OPEN_BRACE.Fields::render($fields, $this->options->delimiter).Constants::CLOSE_BRACE
            .Constants::COLON;
    }

    /**
     * Format a keyed tabular header: `key[N:<delim?>]{fields}:` (§6, §9.5).
     *
     * The colon immediately after the length marks the keyed form; N is the entry count.
     *
     * @param  array<int, array{name: string, children: array<int, mixed>|null}>  $fields
     * @param  string|null  $key  Optional key for the object (omitted at the root)
     */
    private function formatKeyedHeader(int $entryCount, array $fields, ?string $key): string
    {
        $header = $key !== null ? Primitives::encodeKey($key) : '';

        return $header
            .Constants::OPEN_BRACKET.$entryCount.Constants::COLON.$this->getDelimiterKey($this->options->delimiter).Constants::CLOSE_BRACKET
            .Constants::OPEN_BRACE.Fields::render($fields, $this->options->delimiter).Constants::CLOSE_BRACE
            .Constants::COLON;
    }

    /**
     * Keyed tabular detection (§9.5): at least two entries, every entry value a
     * non-empty object, all entry values sharing one uniform shape.
     *
     * @param  array<string, mixed>  $object
     * @return array<int, array{name: string, children: array<int, mixed>|null}>|null
     */
    private static function detectKeyedTabular(array $object): ?array
    {
        if (count($object) < 2) {
            return null;
        }

        return Fields::detect(array_values($object));
    }

    /**
     * @param  array<mixed>  $array
     * @param  array<int, array{name: string, children: array<int, mixed>|null}>  $fields
     */
    private function writeTabularRows(array $array, array $fields, int $depth): void
    {
        foreach ($array as $object) {
            $cells = Fields::cells($fields, $object, $this->options->delimiter);
            $this->writer->push($depth, implode($this->options->delimiter, $cells));
        }
    }

    /**
     * Write one `entrykey: c1<delim>c2…` line per entry, in encounter order (§9.5).
     *
     * @param  array<string, mixed>  $object
     * @param  array<int, array{name: string, children: array<int, mixed>|null}>  $fields
     */
    private function writeEntryRows(array $object, array $fields, int $depth): void
    {
        foreach ($object as $entryKey => $entryValue) {
            $cells = Fields::cells($fields, $entryValue, $this->options->delimiter);
            $this->writer->push(
                $depth,
                Primitives::encodeKey((string) $entryKey).Constants::COLON.Constants::SPACE.implode($this->options->delimiter, $cells)
            );
        }
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function encodeObjectAsListItem(array $object, int $depth): void
    {
        $keys = array_keys($object);
        if ($keys === []) {
            // §10: an empty-object list item is a bare "-" (no trailing space, §12).
            $this->writer->push($depth, Constants::LIST_ITEM_MARKER);

            return;
        }

        // §10: the first field in encounter order always sits on the hyphen line.
        $firstKey = $keys[0];
        $this->encodeKeyValuePair((string) $firstKey, $object[$firstKey], $depth, true);

        // Remaining fields sit at depth +1 under the hyphen line.
        for ($i = 1; $i < count($keys); $i++) {
            $key = $keys[$i];
            $this->encodeKeyValuePair((string) $key, $object[$key], $depth + 1);
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
            // §9.2: the "key: []" field form does not apply to list items; an empty
            // inner array stays "- [0]:".
            if (Normalize::isArrayOfPrimitives($item)) {
                $this->writer->push($depth, Constants::LIST_ITEM_PREFIX.$this->formatInlineArray($item));

                return;
            }

            // §9.4: a nested array of objects or non-uniform array opens a list scope
            // on the hyphen line; tabular form is unavailable in this position.
            $this->writer->push($depth, Constants::LIST_ITEM_PREFIX.$this->formatListHeader(count($item), null));
            foreach ($item as $subItem) {
                $this->encodeMixedArrayItem($subItem, $depth + 1);
            }

            return;
        }

        // Objects
        if (Normalize::isJsonObject($item)) {
            $this->encodeObjectAsListItem(get_object_vars($item), $depth);

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
