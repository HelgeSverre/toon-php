<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Codec\V41;

use HelgeSverre\Toon\Constants;

/**
 * Field lists for tabular and keyed tabular headers (TOON §6, §9.3, §9.5).
 *
 * A field list is an ordered sequence of field entries. A field entry is either
 * a leaf field (a bare name declaring a primitive column) or a name carrying its
 * own nested field group, which declares a nested-uniform column. Row and entry
 * row cells map one-to-one to leaf fields in depth-first, pre-order header order.
 *
 * @phpstan-type FieldEntry array{name: string, children: array<int, mixed>|null}
 *
 * @internal Version-specific codec implementation.
 */
final class Fields
{
    /**
     * Build the field list for a set of candidate objects (§9.3 column classification).
     *
     * Returns null when the objects do not form a uniform table: when any element
     * is not a non-empty object, when key sets differ, or when any column is
     * neither uniform-primitive nor nested-uniform.
     *
     * @param  array<int|string, mixed>  $objects  Candidate objects (array elements or entry values)
     * @return array<int, array{name: string, children: array<int, mixed>|null}>|null
     */
    public static function detect(array $objects): ?array
    {
        $objects = array_values($objects);
        if ($objects === []) {
            return null;
        }

        $expected = null;
        foreach ($objects as $object) {
            // Every element must be a non-empty object; an empty object
            // disqualifies the whole table (§9.3).
            if (! Normalize::isJsonObject($object) || get_object_vars($object) === []) {
                return null;
            }

            $keys = array_map(strval(...), array_keys(get_object_vars($object)));
            sort($keys);

            if ($expected === null) {
                $expected = $keys;
            } elseif ($keys !== $expected) {
                // All objects MUST have the same set of keys (order MAY vary).
                return null;
            }
        }

        $first = $objects[0];
        if (! $first instanceof \stdClass) {
            return null;
        }

        $fields = [];
        foreach (array_keys(get_object_vars($first)) as $name) {
            $column = [];
            foreach ($objects as $object) {
                /** @var mixed $cell */
                $cell = $object instanceof \stdClass ? ($object->{$name} ?? null) : null;
                $column[] = $cell;
            }

            if (self::isUniformPrimitiveColumn($column)) {
                $fields[] = ['name' => (string) $name, 'children' => null];

                continue;
            }

            // A nested-uniform column recurses: every value is a non-empty object
            // with the same key set, and every sub-column is itself uniform.
            $children = self::detect($column);
            if ($children === null) {
                return null;
            }

            $fields[] = ['name' => (string) $name, 'children' => $children];
        }

        return $fields;
    }

    /**
     * Render a field list for a header, joining entries with the active delimiter.
     *
     * Field names at every nesting level are encoded as keys (§7.3).
     *
     * @param  array<int, array{name: string, children: array<int, mixed>|null}>  $fields
     */
    public static function render(array $fields, string $delimiter): string
    {
        $parts = [];
        foreach ($fields as $field) {
            $rendered = Primitives::encodeKey($field['name']);
            if ($field['children'] !== null) {
                /** @var array<int, array{name: string, children: array<int, mixed>|null}> $children */
                $children = $field['children'];
                $rendered .= Constants::OPEN_BRACE.self::render($children, $delimiter).Constants::CLOSE_BRACE;
            }
            $parts[] = $rendered;
        }

        return implode($delimiter, $parts);
    }

    /**
     * Encode one object's primitive leaf values in depth-first, pre-order field order (§9.3).
     *
     * The object is always one the field list was detected from, so every field
     * name is present; the null coalesce only satisfies the type checker.
     *
     * @param  array<int, array{name: string, children: array<int, mixed>|null}>  $fields
     * @return array<int, string>
     */
    public static function cells(array $fields, mixed $object, string $delimiter): array
    {
        $cells = [];
        foreach ($fields as $field) {
            /** @var mixed $value */
            $value = $object instanceof \stdClass ? ($object->{$field['name']} ?? null) : null;

            if ($field['children'] === null) {
                $cells[] = Primitives::encodePrimitive($value, $delimiter);

                continue;
            }

            /** @var array<int, array{name: string, children: array<int, mixed>|null}> $children */
            $children = $field['children'];
            foreach (self::cells($children, $value, $delimiter) as $cell) {
                $cells[] = $cell;
            }
        }

        return $cells;
    }

    /**
     * Count the leaf fields of a field list – the number of cells a row must carry (§9.3).
     *
     * @param  array<int, array{name: string, children: array<int, mixed>|null}>  $fields
     */
    public static function leafCount(array $fields): int
    {
        $count = 0;
        foreach ($fields as $field) {
            if ($field['children'] === null) {
                $count++;

                continue;
            }

            /** @var array<int, array{name: string, children: array<int, mixed>|null}> $children */
            $children = $field['children'];
            $count += self::leafCount($children);
        }

        return $count;
    }

    /**
     * Materialize a decoded row from its cells by walking the field list (§9.3).
     *
     * A leaf field takes the next cell; a nested field group builds an object from
     * its subfields, recursively. Decoded key order at every level is the header's
     * field order. A leaf with no remaining cell is absent from the result, which is
     * how non-strict mode tolerates a short row (§14.1).
     *
     * @param  array<int, array{name: string, children: array<int, mixed>|null}>  $fields
     * @param  array<int, mixed>  $cells
     * @return array<string, mixed>
     */
    public static function materialize(array $fields, array $cells, int &$index): array
    {
        $result = [];
        foreach ($fields as $field) {
            if ($field['children'] === null) {
                if (! array_key_exists($index, $cells)) {
                    $index++;

                    continue;
                }

                $result[$field['name']] = $cells[$index];
                $index++;

                continue;
            }

            /** @var array<int, array{name: string, children: array<int, mixed>|null}> $children */
            $children = $field['children'];
            $result[$field['name']] = self::materialize($children, $cells, $index);
        }

        return $result;
    }

    /**
     * A column is uniform-primitive when every value in it is a primitive (§9.3).
     *
     * @param  array<int, mixed>  $column
     */
    private static function isUniformPrimitiveColumn(array $column): bool
    {
        foreach ($column as $value) {
            if (! Normalize::isJsonPrimitive($value)) {
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
