<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Codec\V44\Decoder;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\Exceptions\CountMismatchException;
use HelgeSverre\Toon\Exceptions\IndentationException;
use HelgeSverre\Toon\Exceptions\StrictModeException;
use HelgeSverre\Toon\Exceptions\SyntaxException;

/**
 * Turns prepared lines into PHP values.
 *
 * Every scope (an object body, list items, table rows, keyed entries) lives at one
 * depth. A scope reads lines at its depth, stops at the first shallower line, and
 * fails on a deeper line that nothing opened.
 */
final class Parser
{
    /** @var list<Line> */
    private array $lines = [];

    private int $pos = 0;

    public function __construct(
        private readonly DecodeOptions $options,
    ) {}

    /**
     * @param  list<Line>  $lines
     */
    public function parse(array $lines): mixed
    {
        $this->lines = $lines;
        $this->pos = 0;

        $first = $this->peek();
        if ($first === null) {
            return [];
        }

        if ($first->depth !== 0) {
            throw $this->unexpectedIndent($first);
        }

        // A header without a key makes the whole document an array (or a keyed object)
        if (HeaderParser::isHeader($first->content)) {
            $header = HeaderParser::parse($first->content, $first->number, $this->options->strict);
            if ($header->key === null) {
                $this->advance();
                $value = $this->readHeaderValue($header, $first, 1);
                $this->expectEnd();

                return $value;
            }
        } elseif ($first->content === '[]') {
            $this->advance();
            $this->expectEnd();

            return [];
        } elseif (DelimiterParser::findUnquoted($first->content, ':') === null && $this->countContentLines() === 1) {
            $this->advance();

            return ValueParser::parseValue($first->content, $first->number);
        }

        return $this->readFields(0);
    }

    /**
     * Read the fields of an object at the given depth.
     *
     * @param  array<array-key, mixed>  $object  fields already read, e.g. from a list item's hyphen line
     * @return array<array-key, mixed>
     */
    private function readFields(int $depth, array $object = []): array
    {
        while (($line = $this->peek()) !== null && $line->depth >= $depth) {
            if ($line->depth > $depth) {
                throw $this->unexpectedIndent($line);
            }

            $this->advance();
            $this->readField($line, $line->content, $depth + 1, $object);
        }

        return $object;
    }

    /**
     * Read one "key: value" or "key[N]...:" field into $object. Anything the field
     * opens is expected at $childDepth.
     *
     * @param  array<array-key, mixed>  $object
     */
    private function readField(Line $line, string $content, int $childDepth, array &$object): void
    {
        if (HeaderParser::isHeader($content)) {
            $header = HeaderParser::parse($content, $line->number, $this->options->strict);
            if ($header->key === null) {
                throw new SyntaxException('An array header without a key is only allowed at the root or as a list item', $line->number, $line->content);
            }

            $this->set($object, $header->key, $this->readHeaderValue($header, $line, $childDepth), $line);

            return;
        }

        $colon = DelimiterParser::findUnquoted($content, ':');
        if ($colon === null) {
            throw new SyntaxException('Missing colon after key', $line->number, $line->content);
        }

        $key = ValueParser::parseKey(substr($content, 0, $colon), $line->number);
        $rest = trim(substr($content, $colon + 1), ' ');

        if ($rest === '') {
            $depth = $this->scopeDepth($childDepth);
            $value = $depth === null ? [] : $this->readFields($depth);
        } elseif ($rest === '[]') {
            $value = [];
        } else {
            $value = ValueParser::parseValue($rest, $line->number);
        }

        $this->set($object, $key, $value, $line);
    }

    private function readHeaderValue(Header $header, Line $line, int $childDepth): mixed
    {
        if ($header->keyed) {
            return $this->readEntries($header, $line, $childDepth);
        }

        if ($header->fields !== null) {
            return $this->readRows($header, $line, $childDepth);
        }

        if ($header->inline !== '') {
            $values = $this->splitValues($header->inline, $header->delimiter, $line);
            $this->checkCount($header->length, count($values), 'Array length mismatch: expected %d, got %d', $line);

            return $values;
        }

        return $this->readListItems($header, $line, $childDepth);
    }

    /**
     * @return list<mixed>
     */
    private function readListItems(Header $header, Line $headerLine, int $expectedDepth): array
    {
        $depth = $this->scopeDepth($expectedDepth);
        $start = $this->pos;
        $items = [];

        while ($depth !== null && ($line = $this->peek()) !== null && $line->depth >= $depth) {
            if ($line->depth > $depth) {
                throw $this->unexpectedIndent($line);
            }

            // A line at item depth without a hyphen ends the list
            if ($line->content !== '-' && ! str_starts_with($line->content, '- ')) {
                break;
            }

            $this->advance();
            $items[] = $this->readListItem($line, $depth);
        }

        $this->checkBlankLines($start);
        $this->checkCount($header->length, count($items), 'Array length mismatch: expected %d, got %d', $headerLine);

        return $items;
    }

    private function readListItem(Line $line, int $depth): mixed
    {
        $rest = ltrim(substr($line->content, 1), ' ');

        // A bare hyphen is an empty object
        if ($rest === '' || $rest === '[]') {
            return [];
        }

        if (HeaderParser::isHeader($rest)) {
            $header = HeaderParser::parse($rest, $line->number, $this->options->strict);

            // "- [N]: ..." is the item itself, so its own items sit one level below the hyphen
            if ($header->key === null) {
                if ($header->fields !== null) {
                    throw new SyntaxException('A table header without a key is only allowed at the root', $line->number, $line->content);
                }

                return $this->readHeaderValue($header, $line, $depth + 1);
            }
        } elseif (DelimiterParser::findUnquoted($rest, ':') === null) {
            return ValueParser::parseValue($rest, $line->number);
        }

        // An object: the first field is on the hyphen line and counts as one level
        // deeper, so the other fields sit at depth + 1 and its own content at depth + 2
        $object = [];
        $this->readField($line, $rest, $depth + 2, $object);

        return $this->readFields($depth + 1, $object);
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function readRows(Header $header, Line $headerLine, int $expectedDepth): array
    {
        $depth = $this->scopeDepth($expectedDepth);
        $start = $this->pos;
        $rows = [];

        while ($depth !== null && ($line = $this->peek()) !== null && $line->depth >= $depth) {
            if ($line->depth > $depth) {
                throw $this->unexpectedIndent($line);
            }

            // A colon before any delimiter means this is a "key: value" line, not a row
            $colon = DelimiterParser::findUnquoted($line->content, ':');
            $delimiter = DelimiterParser::findUnquoted($line->content, $header->delimiter);
            if ($colon !== null && ($delimiter === null || $colon < $delimiter)) {
                break;
            }

            $this->advance();
            $rows[] = $this->buildRow($header, $line->content, $line);
        }

        $this->checkBlankLines($start);
        $this->checkCount($header->length, count($rows), 'Tabular array length mismatch: expected %d rows, got %d', $headerLine);

        return $rows;
    }

    /**
     * Read "entrykey: cells" lines of a keyed table into an object.
     *
     * @return array<array-key, mixed>
     */
    private function readEntries(Header $header, Line $headerLine, int $expectedDepth): array
    {
        $depth = $this->scopeDepth($expectedDepth);
        $start = $this->pos;
        $object = [];
        $count = 0;

        while ($depth !== null && ($line = $this->peek()) !== null && $line->depth >= $depth) {
            if ($line->depth > $depth) {
                throw $this->unexpectedIndent($line);
            }

            $colon = DelimiterParser::findUnquoted($line->content, ':');
            if ($colon === null) {
                throw new SyntaxException('Missing colon after entry key', $line->number, $line->content);
            }

            $this->advance();
            $key = ValueParser::parseKey(substr($line->content, 0, $colon), $line->number);
            $this->set($object, $key, $this->buildRow($header, substr($line->content, $colon + 1), $line), $line);
            $count++;
        }

        $this->checkBlankLines($start);
        $this->checkCount($header->length, $count, 'Keyed table length mismatch: expected %d rows, got %d', $headerLine);

        return $object;
    }

    /**
     * Turn the cells of one row into an object following the header's fields.
     *
     * @return array<array-key, mixed>
     */
    private function buildRow(Header $header, string $cellText, Line $line): array
    {
        $fields = $header->fields ?? [];
        $cells = $this->splitValues($cellText, $header->delimiter, $line);
        $expected = HeaderParser::leafCount($fields);

        if (count($cells) !== $expected) {
            throw new CountMismatchException(
                sprintf('Tabular row width mismatch: expected %d values, got %d', $expected, count($cells)),
                $expected,
                count($cells),
                $line->number,
                $line->content,
            );
        }

        $index = 0;

        return $this->fillFields($fields, $cells, $index);
    }

    /**
     * @param  list<Field>  $fields
     * @param  list<mixed>  $cells
     * @return array<array-key, mixed>
     */
    private function fillFields(array $fields, array $cells, int &$index): array
    {
        $object = [];
        foreach ($fields as $field) {
            $object[$field->name] = $field->children === null
                ? $cells[$index++]
                : $this->fillFields($field->children, $cells, $index);
        }

        return $object;
    }

    /**
     * @return list<mixed>
     */
    private function splitValues(string $text, string $delimiter, Line $line): array
    {
        return array_map(
            static fn (string $token): mixed => ValueParser::parseValue($token, $line->number),
            DelimiterParser::split($text, $delimiter, $line->number),
        );
    }

    /**
     * Depth of a nested scope expected at $expected, or null when the scope is empty.
     * A first line deeper than expected is an error, or in non-strict mode sets the
     * depth for the rest of the scope.
     */
    private function scopeDepth(int $expected): ?int
    {
        $next = $this->peek();
        if ($next === null || $next->depth < $expected) {
            return null;
        }

        if ($next->depth > $expected && $this->options->strict) {
            throw new IndentationException("Indentation jumps from depth {$expected} to {$next->depth}", $next->number, $next->content);
        }

        return $next->depth;
    }

    /**
     * @param  array<array-key, mixed>  $object
     */
    private function set(array &$object, string $key, mixed $value, Line $line): void
    {
        // Non-strict mode lets the last value win, keeping the key where it first appeared
        if ($this->options->strict && array_key_exists($key, $object)) {
            throw new StrictModeException("Duplicate object key: {$key}", $line->number, $line->content);
        }

        $object[$key] = $value;
    }

    private function checkCount(int $expected, int $actual, string $format, Line $line): void
    {
        if ($this->options->strict && $expected !== $actual) {
            throw new CountMismatchException(sprintf($format, $expected, $actual), $expected, $actual, $line->number, $line->content);
        }
    }

    /**
     * In strict mode, no blank line may appear between the first and last line of an
     * array's items, rows or entries (nested lines included).
     */
    private function checkBlankLines(int $start): void
    {
        if (! $this->options->strict) {
            return;
        }

        while ($start < $this->pos && $this->lines[$start]->blank) {
            $start++;
        }

        for ($i = $start; $i < $this->pos; $i++) {
            if ($this->lines[$i]->blank) {
                throw new StrictModeException('Blank lines not allowed inside arrays/tabular rows (strict mode)', $this->lines[$i]->number);
            }
        }
    }

    /**
     * After a root array or keyed table nothing else may follow.
     */
    private function expectEnd(): void
    {
        $line = $this->peek();
        if ($line !== null) {
            throw new SyntaxException('Unexpected content after the end of the root value', $line->number, $line->content);
        }
    }

    private function unexpectedIndent(Line $line): SyntaxException
    {
        return new SyntaxException('Unexpected indentation', $line->number, $line->content);
    }

    private function countContentLines(): int
    {
        return count(array_filter($this->lines, static fn (Line $line): bool => ! $line->blank));
    }

    /**
     * The next non-blank line, without consuming it.
     */
    private function peek(): ?Line
    {
        for ($i = $this->pos; $i < count($this->lines); $i++) {
            if (! $this->lines[$i]->blank) {
                return $this->lines[$i];
            }
        }

        return null;
    }

    /**
     * Consume the line returned by peek().
     */
    private function advance(): void
    {
        while ($this->lines[$this->pos]->blank) {
            $this->pos++;
        }
        $this->pos++;
    }
}
