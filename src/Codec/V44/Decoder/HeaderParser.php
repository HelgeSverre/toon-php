<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Codec\V44\Decoder;

use HelgeSverre\Toon\Exceptions\SyntaxException;

/**
 * Parses array headers like key[N]:, key[N|]{a|b}: and the keyed form key[N:]{a,b}:.
 */
final class HeaderParser
{
    /**
     * A line is a header when it has a colon and an opening bracket comes before it,
     * not counting anything inside quotes.
     */
    public static function isHeader(string $content): bool
    {
        $colon = DelimiterParser::findUnquoted($content, ':');
        $bracket = DelimiterParser::findUnquoted($content, '[');

        return $colon !== null && $bracket !== null && $bracket < $colon;
    }

    public static function parse(string $content, int $line, bool $strict): Header
    {
        $open = (int) DelimiterParser::findUnquoted($content, '[');

        $key = null;
        $keyText = substr($content, 0, $open);
        if ($keyText !== '') {
            if (rtrim($keyText, " \t") !== $keyText) {
                throw self::malformed('space before the bracket', $line, $content);
            }
            $key = ValueParser::parseKey($keyText, $line);
        }

        $match = [];
        if (! preg_match('/\G\[(0|[1-9][0-9]*)(:?)([\t|]?)\]/', $content, $match, 0, $open)) {
            $close = strpos($content, ']', $open);
            if (str_contains(substr($content, $open, $close === false ? null : $close - $open), '#')) {
                throw new SyntaxException('Invalid array header format: [#N] syntax is not supported in TOON v2.0. Use [N] instead.', $line, $content);
            }

            throw self::malformed('bad length or delimiter', $line, $content);
        }

        $delimiter = match ($match[3]) {
            "\t" => "\t",
            '|' => '|',
            default => ',',
        };
        $keyed = $match[2] === ':';
        $pos = $open + strlen($match[0]);

        $fields = null;
        if (($content[$pos] ?? '') === '{') {
            $fields = self::parseFields($content, $pos, $delimiter, $line, $strict);
        }

        if (($content[$pos] ?? '') !== ':') {
            throw self::malformed('expected a colon', $line, $content);
        }

        if ($keyed && $fields === null) {
            throw self::malformed('a keyed header needs a field list', $line, $content);
        }

        $inline = trim(substr($content, $pos + 1), ' ');
        if ($fields !== null && $inline !== '') {
            throw self::malformed('nothing may follow the colon of a table header', $line, $content);
        }

        return new Header($key, (int) $match[1], $keyed, $delimiter, $fields, $inline);
    }

    /**
     * Number of cells a row needs: nested groups count their own fields.
     *
     * @param  list<Field>  $fields
     */
    public static function leafCount(array $fields): int
    {
        $count = 0;
        foreach ($fields as $field) {
            $count += $field->children === null ? 1 : self::leafCount($field->children);
        }

        return $count;
    }

    /**
     * Parse a {...} field list starting at $pos and move $pos past the closing brace.
     *
     * @return list<Field>
     */
    private static function parseFields(string $content, int &$pos, string $delimiter, int $line, bool $strict): array
    {
        $fields = [];
        $names = [];
        $len = strlen($content);
        $pos++;

        while (true) {
            // Read a name up to the next delimiter or brace outside quotes
            $start = $pos;
            $inQuotes = false;
            for (; $pos < $len; $pos++) {
                $char = $content[$pos];
                if ($inQuotes) {
                    if ($char === '\\') {
                        $pos++;
                    } elseif ($char === '"') {
                        $inQuotes = false;
                    }
                } elseif ($char === '"') {
                    $inQuotes = true;
                } elseif (str_contains("{},|\t", $char)) {
                    break;
                }
            }

            $raw = substr($content, $start, $pos - $start);
            if (trim($raw, ' ') === '') {
                throw self::malformed('empty field name', $line, $content);
            }
            $name = ValueParser::parseKey($raw, $line);

            if ($strict && in_array($name, $names, true)) {
                throw self::malformed("duplicate field {$name}", $line, $content);
            }
            $names[] = $name;

            $children = null;
            if (($content[$pos] ?? '') === '{') {
                if (rtrim($raw, ' ') !== $raw) {
                    throw self::malformed('space before a field group', $line, $content);
                }
                $children = self::parseFields($content, $pos, $delimiter, $line, $strict);
                $pos += strspn($content, ' ', $pos);
            }
            $fields[] = new Field($name, $children);

            $char = $content[$pos] ?? '';
            $pos++;
            if ($char === '}') {
                return $fields;
            }
            if ($char !== $delimiter) {
                throw self::malformed('unexpected character in field list', $line, $content);
            }
        }
    }

    private static function malformed(string $reason, int $line, string $content): SyntaxException
    {
        return new SyntaxException("Malformed array header: {$reason}", $line, $content);
    }
}
