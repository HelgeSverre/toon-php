<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Codec\V44;

use HelgeSverre\Toon\Constants;
use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\LineWriter;

final class Encoders
{
    public function __construct(
        private readonly EncodeOptions $options,
        private readonly LineWriter $writer,
    ) {}

    /**
     * Encode any value to TOON format, writing output to a LineWriter.
     *
     * Handles primitives, arrays, and objects. Automatically selects the most
     * compact representation (inline arrays, tables, keyed tables, or list format).
     *
     * @param  mixed  $value  The value to encode
     * @param  non-negative-int  $depth  Current indentation depth (default: 0)
     */
    public function encodeValue(mixed $value, int $depth = 0): void
    {
        // A root string starting with U+FEFF would be read back as a byte-order mark
        if (is_string($value) && str_starts_with($value, "\u{FEFF}")) {
            $this->writer->push($depth, Constants::DOUBLE_QUOTE.Primitives::escapeString($value).Constants::DOUBLE_QUOTE);

            return;
        }

        if (Normalize::isJsonPrimitive($value)) {
            $this->writer->push($depth, Primitives::encodePrimitive($value, $this->options->delimiter));

            return;
        }

        // An empty object at the root produces no output
        if (Normalize::isEmptyObject($value)) {
            return;
        }

        // An empty list at the root is written as []
        if ($value === []) {
            $this->writer->push($depth, Constants::EMPTY_ARRAY);

            return;
        }

        if (Normalize::isJsonArray($value)) {
            $this->encodeArray(null, $value, $depth, '', $depth + 1, true);

            return;
        }

        if (Normalize::isJsonObject($value)) {
            $value = get_object_vars($value);
            $fields = $this->keyedFields($value);
            if ($fields !== null) {
                $this->encodeKeyedObject('', $value, $fields, $depth, '', $depth + 1);

                return;
            }

            $this->encodeObject($value, $depth);

            return;
        }

        $this->writer->push($depth, Constants::NULL_LITERAL);
    }

    /**
     * @param  array<array-key, mixed>  $object
     * @param  non-negative-int  $depth
     */
    private function encodeObject(array $object, int $depth): void
    {
        foreach ($object as $key => $value) {
            $this->encodeField((string) $key, $value, $depth, '', $depth + 1);
        }
    }

    /**
     * Write one object field. The prefix is "- " when the field sits on a list item's
     * hyphen line, and childDepth is where anything nested under the field goes.
     *
     * @param  non-negative-int  $depth
     * @param  non-negative-int  $childDepth
     */
    private function encodeField(string $key, mixed $value, int $depth, string $prefix, int $childDepth): void
    {
        $name = Primitives::encodeKey($key);

        if (Normalize::isJsonPrimitive($value)) {
            $encoded = Primitives::encodePrimitive($value, $this->options->delimiter);
            $this->writer->push($depth, $prefix.$name.Constants::COLON.Constants::SPACE.$encoded);

            return;
        }

        if ($value === []) {
            $this->writer->push($depth, $prefix.$name.Constants::COLON.Constants::SPACE.Constants::EMPTY_ARRAY);

            return;
        }

        if (Normalize::isJsonArray($value)) {
            $this->encodeArray($name, $value, $depth, $prefix, $childDepth, true);

            return;
        }

        if (Normalize::isJsonObject($value)) {
            $value = get_object_vars($value);
            $fields = $this->keyedFields($value);
            if ($fields !== null) {
                $this->encodeKeyedObject($name, $value, $fields, $depth, $prefix, $childDepth);

                return;
            }

            $this->writer->push($depth, $prefix.$name.Constants::COLON);
            $this->encodeObject($value, $childDepth);

            return;
        }

        $this->writer->push($depth, $prefix.$name.Constants::COLON.Constants::SPACE.Constants::NULL_LITERAL);
    }

    /**
     * Write a non-empty array under its header. The name is the already encoded key,
     * or null for a keyless header. Tables are not allowed for arrays that are
     * themselves list items, since a keyless table header only works at the root.
     *
     * @param  list<mixed>  $array
     * @param  non-negative-int  $depth
     * @param  non-negative-int  $childDepth
     */
    private function encodeArray(?string $name, array $array, int $depth, string $prefix, int $childDepth, bool $tableAllowed): void
    {
        if (Normalize::isArrayOfPrimitives($array)) {
            $this->writer->push($depth, $prefix.$this->formatInlineArray($name, $array));

            return;
        }

        $fields = $tableAllowed ? $this->tableFields($array) : null;
        if ($fields !== null) {
            $this->writer->push($depth, $prefix.($name ?? '').$this->bracket(count($array)).$this->formatFields($fields).Constants::COLON);
            foreach ($array as $row) {
                if ($row instanceof \stdClass) {
                    $this->writer->push($childDepth, implode($this->options->delimiter, $this->rowCells($row, $fields)));
                }
            }

            return;
        }

        $this->writer->push($depth, $prefix.($name ?? '').$this->bracket(count($array)).Constants::COLON);
        foreach ($array as $item) {
            $this->encodeListItem($item, $childDepth);
        }
    }

    /**
     * Write an object as a keyed table: one header, then "entrykey: cells" per entry.
     *
     * @param  array<array-key, mixed>  $object
     * @param  array<array-key, mixed>  $fields
     * @param  non-negative-int  $depth
     * @param  non-negative-int  $childDepth
     */
    private function encodeKeyedObject(string $name, array $object, array $fields, int $depth, string $prefix, int $childDepth): void
    {
        $bracket = Constants::OPEN_BRACKET.count($object).Constants::COLON.$this->getDelimiterKey($this->options->delimiter).Constants::CLOSE_BRACKET;
        $this->writer->push($depth, $prefix.$name.$bracket.$this->formatFields($fields).Constants::COLON);

        foreach ($object as $key => $entry) {
            if ($entry instanceof \stdClass) {
                $cells = implode($this->options->delimiter, $this->rowCells($entry, $fields));
                $this->writer->push($childDepth, Primitives::encodeKey((string) $key).Constants::COLON.Constants::SPACE.$cells);
            }
        }
    }

    /**
     * @param  non-negative-int  $depth
     */
    private function encodeListItem(mixed $item, int $depth): void
    {
        if (Normalize::isJsonPrimitive($item)) {
            $encoded = Primitives::encodePrimitive($item, $this->options->delimiter);
            $this->writer->push($depth, Constants::LIST_ITEM_PREFIX.$encoded);

            return;
        }

        // Inner arrays always get a header, so an empty one is "- [0]:" rather than "- []"
        if ($item === [] || (Normalize::isJsonArray($item) && Normalize::isArrayOfPrimitives($item))) {
            $this->writer->push($depth, Constants::LIST_ITEM_PREFIX.$this->formatInlineArray(null, $item));

            return;
        }

        if (Normalize::isJsonArray($item)) {
            $this->encodeArray(null, $item, $depth, Constants::LIST_ITEM_PREFIX, $depth + 1, false);

            return;
        }

        // The first field goes on the hyphen line, the rest line up one level below it.
        // Anything nested under a field sits one level deeper still.
        if (Normalize::isJsonObject($item) && ! Normalize::isEmptyObject($item)) {
            $item = get_object_vars($item);
            $prefix = Constants::LIST_ITEM_PREFIX;
            $fieldDepth = $depth;
            foreach ($item as $key => $value) {
                $this->encodeField((string) $key, $value, $fieldDepth, $prefix, $depth + 2);
                $prefix = '';
                $fieldDepth = $depth + 1;
            }

            return;
        }

        // Empty object: just the hyphen, without a trailing space
        if (Normalize::isEmptyObject($item)) {
            $this->writer->push($depth, Constants::LIST_ITEM_MARKER);

            return;
        }

        $this->writer->push($depth, Constants::LIST_ITEM_PREFIX.Constants::NULL_LITERAL);
    }

    /**
     * @param  array<mixed>  $array
     */
    private function formatInlineArray(?string $name, array $array): string
    {
        $encoded = array_map(
            fn ($item) => Primitives::encodePrimitive($item, $this->options->delimiter),
            $array,
        );
        $joined = implode($this->options->delimiter, $encoded);

        // Only add space after colon if there are items
        return ($name ?? '').$this->bracket(count($array)).Constants::COLON.($joined !== '' ? Constants::SPACE.$joined : '');
    }

    /**
     * The "[N]" part of a header, with the delimiter symbol when it isn't a comma.
     */
    private function bracket(int $length): string
    {
        return Constants::OPEN_BRACKET.$length.$this->getDelimiterKey($this->options->delimiter).Constants::CLOSE_BRACKET;
    }

    /**
     * Format a field list such as {id,customer{name,country}}.
     *
     * @param  array<array-key, mixed>  $fields
     */
    private function formatFields(array $fields): string
    {
        $parts = [];
        foreach ($fields as $key => $nested) {
            $parts[] = Primitives::encodeKey((string) $key).(is_array($nested) ? $this->formatFields($nested) : '');
        }

        return Constants::OPEN_BRACE.implode($this->options->delimiter, $parts).Constants::CLOSE_BRACE;
    }

    /**
     * Work out the header fields for objects written as table rows.
     *
     * Every object must have the same keys. A key holding only primitives becomes a
     * plain field (null here), and a key holding objects that share one shape becomes
     * a nested group like customer{name,country}. Returns null when the objects
     * can't share a header.
     *
     * @param  array<mixed>  $objects
     * @return array<array-key, mixed>|null
     */
    private function tableFields(array $objects): ?array
    {
        $keys = null;
        $first = null;
        $rows = [];
        foreach ($objects as $object) {
            if (! Normalize::isJsonObject($object)) {
                return null;
            }

            $object = get_object_vars($object);
            if ($object === []) {
                return null;
            }

            $objectKeys = array_keys($object);
            sort($objectKeys, SORT_STRING);
            $keys ??= $objectKeys;
            $first ??= $object;
            if ($objectKeys !== $keys) {
                return null;
            }
            $rows[] = $object;
        }

        if ($first === null) {
            return null;
        }

        $fields = [];
        foreach (array_keys($first) as $key) {
            $column = array_column($rows, $key);
            if (Normalize::isArrayOfPrimitives($column)) {
                $fields[$key] = null;

                continue;
            }

            $nested = $this->tableFields($column);
            if ($nested === null) {
                return null;
            }
            $fields[$key] = $nested;
        }

        return $fields;
    }

    /**
     * Fields for writing an object as a keyed table, or null when it doesn't qualify.
     * That takes at least two entries whose values all fit one table header.
     *
     * @param  array<array-key, mixed>  $object
     * @return array<array-key, mixed>|null
     */
    private function keyedFields(array $object): ?array
    {
        return count($object) >= 2 ? $this->tableFields(array_values($object)) : null;
    }

    /**
     * The encoded cells of one row, walking nested groups depth-first.
     *
     * @param  array<array-key, mixed>  $fields
     * @return list<string>
     */
    private function rowCells(\stdClass $object, array $fields): array
    {
        $object = get_object_vars($object);
        $cells = [];
        foreach ($fields as $key => $nested) {
            $value = $object[$key] ?? null;
            if (is_array($nested) && $value instanceof \stdClass) {
                array_push($cells, ...$this->rowCells($value, $nested));
            } else {
                $cells[] = Primitives::encodePrimitive($value, $this->options->delimiter);
            }
        }

        return $cells;
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
