<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Codec\V41\Decoder;

use HelgeSverre\Toon\Constants;
use HelgeSverre\Toon\Exceptions\SyntaxException;

/**
 * Parses TOON array and keyed tabular headers (§6).
 *
 * Handles every header shape:
 * - `[N<delim?>]:` / `key[N<delim?>]:`            – inline or list form
 * - `key[N<delim?>]{f1<delim>f2}:`                – tabular form (§9.3)
 * - `key[N<delim?>]{f1<delim>f2{s1<delim>s2}}:`   – tabular form with nested field groups
 * - `key[N:<delim?>]{f1<delim>f2}:`               – keyed tabular form (§9.5)
 *
 * A line that is not a header at all returns null with no defect, so the caller
 * classifies it per §5.2. A line that attempts a header but fails the grammar
 * returns null with a defect message: strict decoders MUST error, non-strict ones
 * MAY fall through to key-value parsing (§6, §14.2).
 *
 * @internal Version-specific codec implementation.
 */
final class HeaderParser
{
    /**
     * Parse a TOON header line.
     *
     * @param  string|null  $defect  Set to a diagnostic when the line attempts a header but is malformed
     * @return array{key: ?string, hasKey: bool, length: ?int, keyed: bool, delimiter: string, fields: ?array<int, array{name: string, children: array<int, mixed>|null}>, inlineValues: ?string, format: string}|null
     *
     * @throws SyntaxException On errors that are errors in any mode (§14.2)
     */
    public static function parseHeader(string $line, ?string &$defect = null): ?array
    {
        $defect = null;

        $line = trim($line, ' ');
        if ($line === '') {
            return null;
        }

        // §5.2: a line whose first unquoted colon precedes its first unquoted "["
        // is a key-value line, never a header. Only unquoted occurrences count.
        $bracketPos = self::findUnquoted($line, '[');
        if ($bracketPos === false) {
            return null;
        }

        $colonPos = self::findUnquoted($line, ':');
        if ($colonPos !== false && $colonPos < $bracketPos) {
            return null;
        }

        $keyToken = substr($line, 0, $bracketPos);
        $hasKey = $keyToken !== '';
        $key = null;

        if ($hasKey) {
            // §6: whitespace MUST NOT appear between a key and its bracket segment.
            if (rtrim($keyToken, " \t") !== $keyToken) {
                $defect = 'Whitespace between key and bracket segment';

                return null;
            }

            // §7.4: any token is accepted as a literal key, quoted keys unescaped.
            $key = ValueParser::parseKey($keyToken, 0);
        }

        return self::parseBracketSegment($line, $bracketPos, $key, $hasKey, $defect);
    }

    /**
     * @param  string|null  $defect  Set to a diagnostic when the header is malformed
     * @return array{key: ?string, hasKey: bool, length: ?int, keyed: bool, delimiter: string, fields: ?array<int, array{name: string, children: array<int, mixed>|null}>, inlineValues: ?string, format: string}|null
     */
    private static function parseBracketSegment(string $line, int $pos, ?string $key, bool $hasKey, ?string &$defect): ?array
    {
        $len = strlen($line);
        $pos++; // skip [

        // Length: a non-negative integer with no leading zeros (§6).
        $numStart = $pos;
        while ($pos < $len && $line[$pos] >= '0' && $line[$pos] <= '9') {
            $pos++;
        }

        if ($pos === $numStart) {
            // "key[]:" is a malformed bracket segment; the "key: []" value form is
            // handled by the caller and is unaffected (§6, §9.1).
            $defect = 'Malformed bracket segment: missing length';

            return null;
        }

        $lengthToken = substr($line, $numStart, $pos - $numStart);
        if (strlen($lengthToken) > 1 && $lengthToken[0] === '0') {
            $defect = "Malformed bracket segment: leading zeros in length [{$lengthToken}]";

            return null;
        }

        $length = (int) $lengthToken;

        // A colon in exactly this position marks the keyed tabular form (§6, §9.5).
        $keyed = false;
        if ($pos < $len && $line[$pos] === Constants::COLON) {
            $keyed = true;
            $pos++;
        }

        $delimiter = Constants::DELIMITER_COMMA;
        if ($pos < $len && ($line[$pos] === Constants::DELIMITER_PIPE || $line[$pos] === Constants::DELIMITER_TAB)) {
            $delimiter = $line[$pos];
            $pos++;
        }

        if ($pos >= $len || $line[$pos] !== Constants::CLOSE_BRACKET) {
            $defect = 'Malformed bracket segment: expected "]"';

            return null;
        }
        $pos++; // skip ]

        // Optional field list.
        $fields = null;
        if (substr($line, $pos, 1) === Constants::OPEN_BRACE) {
            $braceEnd = self::findMatchingBrace($line, $pos);
            if ($braceEnd === false) {
                $defect = 'Malformed field list: unmatched brace';

                return null;
            }

            $fields = self::parseFieldList(substr($line, $pos + 1, $braceEnd - $pos - 1), $delimiter, $defect);
            if ($fields === null) {
                return null;
            }

            $pos = $braceEnd + 1;
        }

        // §6/§9.5: a keyed header MUST carry a field list.
        if ($keyed && $fields === null) {
            $defect = 'Keyed header requires a field list';

            return null;
        }

        // §6: a colon MUST follow the bracket segment and optional field list, with
        // no intervening content.
        if ($pos >= $len || $line[$pos] !== Constants::COLON) {
            $defect = 'Malformed header: expected ":" after bracket segment';

            return null;
        }
        $pos++; // skip :

        $remaining = trim(substr($line, $pos), ' ');

        if ($fields !== null) {
            // §6: a fields-bearing header carries no inline content.
            if ($remaining !== '') {
                $defect = 'Content after a fields-bearing header\'s colon';

                return null;
            }

            return [
                'key' => $key,
                'hasKey' => $hasKey,
                'length' => $length,
                'keyed' => $keyed,
                'delimiter' => $delimiter,
                'fields' => $fields,
                'inlineValues' => null,
                'format' => $keyed ? 'keyed' : 'tabular',
            ];
        }

        // A non-keyed header without a field list: content after the colon is an
        // inline primitive array; nothing after it opens a block scope (§9.1-§9.4).
        return [
            'key' => $key,
            'hasKey' => $hasKey,
            'length' => $length,
            'keyed' => false,
            'delimiter' => $delimiter,
            'fields' => null,
            'inlineValues' => $remaining !== '' ? $remaining : null,
            'format' => $remaining !== '' || $length === 0 ? 'inline' : 'list',
        ];
    }

    /**
     * Parse a field list's inner text into field entries, recursing into nested groups (§6, §9.3).
     *
     * @param  string|null  $defect  Set to a diagnostic when the field list is malformed
     * @return array<int, array{name: string, children: array<int, mixed>|null}>|null
     */
    private static function parseFieldList(string $inner, string $delimiter, ?string &$defect): ?array
    {
        // §6/§14.2: an empty field list is a header syntax error at every nesting level.
        if (trim($inner, ' ') === '') {
            $defect = 'Malformed field list: empty field list';

            return null;
        }

        $entries = self::splitTopLevel($inner, $delimiter);
        if ($entries === null) {
            $defect = 'Malformed field list: unmatched brace';

            return null;
        }

        $fields = [];
        foreach ($entries as $entry) {
            $entry = trim($entry, ' ');
            if ($entry === '') {
                $defect = 'Malformed field list: empty field entry';

                return null;
            }

            // Split the entry into its name and an optional nested field group.
            $groupStart = self::findUnquoted($entry, Constants::OPEN_BRACE);
            $nameToken = $groupStart === false ? $entry : substr($entry, 0, $groupStart);
            $nameToken = trim($nameToken, ' ');

            if ($nameToken === '') {
                $defect = 'Malformed field list: empty field name';

                return null;
            }

            // §6/§14.2: the field list splits on the bracket segment's delimiter; an
            // unquoted field name still carrying a different delimiter means the two
            // segments disagree.
            if (! str_starts_with($nameToken, Constants::DOUBLE_QUOTE)) {
                foreach ([Constants::DELIMITER_COMMA, Constants::DELIMITER_PIPE, Constants::DELIMITER_TAB] as $other) {
                    if ($other !== $delimiter && str_contains($nameToken, $other)) {
                        $defect = 'Header delimiter mismatch between bracket segment and field list';

                        return null;
                    }
                }
            }

            $name = ValueParser::parseKey($nameToken, 0);

            $children = null;
            if ($groupStart !== false) {
                $groupEnd = self::findMatchingBrace($entry, $groupStart);
                if ($groupEnd === false || $groupEnd !== strlen($entry) - 1) {
                    $defect = 'Malformed field list: unmatched brace';

                    return null;
                }

                $children = self::parseFieldList(substr($entry, $groupStart + 1, $groupEnd - $groupStart - 1), $delimiter, $defect);
                if ($children === null) {
                    return null;
                }
            }

            $fields[] = ['name' => $name, 'children' => $children];
        }

        return $fields;
    }

    /**
     * Split a field list's inner text on the active delimiter, at brace depth 0 only.
     *
     * Returns null when braces are unbalanced.
     *
     * @return array<int, string>|null
     */
    private static function splitTopLevel(string $input, string $delimiter): ?array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $inQuotes = false;
        $len = strlen($input);

        for ($i = 0; $i < $len; $i++) {
            $char = $input[$i];

            if ($inQuotes) {
                $current .= $char;
                if ($char === Constants::BACKSLASH && $i + 1 < $len) {
                    $current .= $input[$i + 1];
                    $i++;

                    continue;
                }
                if ($char === Constants::DOUBLE_QUOTE) {
                    $inQuotes = false;
                }

                continue;
            }

            if ($char === Constants::DOUBLE_QUOTE) {
                $inQuotes = true;
                $current .= $char;

                continue;
            }

            if ($char === Constants::OPEN_BRACE) {
                $depth++;
            } elseif ($char === Constants::CLOSE_BRACE) {
                $depth--;
                if ($depth < 0) {
                    return null;
                }
            } elseif ($char === $delimiter && $depth === 0) {
                $parts[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if ($depth !== 0 || $inQuotes) {
            return null;
        }

        $parts[] = $current;

        return $parts;
    }

    /**
     * Index of the "}" matching the "{" at $start, or false when unmatched.
     *
     * Brace matching ignores "{" and "}" inside quoted names (§6).
     */
    private static function findMatchingBrace(string $line, int $start): int|false
    {
        $depth = 0;
        $inQuotes = false;
        $len = strlen($line);

        for ($i = $start; $i < $len; $i++) {
            $char = $line[$i];

            if ($inQuotes) {
                if ($char === Constants::BACKSLASH && $i + 1 < $len) {
                    $i++;

                    continue;
                }
                if ($char === Constants::DOUBLE_QUOTE) {
                    $inQuotes = false;
                }

                continue;
            }

            if ($char === Constants::DOUBLE_QUOTE) {
                $inQuotes = true;
            } elseif ($char === Constants::OPEN_BRACE) {
                $depth++;
            } elseif ($char === Constants::CLOSE_BRACE) {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return false;
    }

    /**
     * Position of the first character from $needles that lies outside a quoted
     * span, or false if none is found. Mirrors the decoder's quote handling:
     * inside a quoted string a backslash escapes the next character (§7.1), so
     * an escaped quote does not end the string.
     */
    public static function findUnquoted(string $line, string $needles): int|false
    {
        $inQuotes = false;
        $len = strlen($line);

        for ($i = 0; $i < $len; $i++) {
            $char = $line[$i];

            if ($inQuotes) {
                if ($char === Constants::BACKSLASH && $i + 1 < $len) {
                    $i++;

                    continue;
                }
                if ($char === Constants::DOUBLE_QUOTE) {
                    $inQuotes = false;
                }

                continue;
            }

            if ($char === Constants::DOUBLE_QUOTE) {
                $inQuotes = true;
            } elseif (str_contains($needles, $char)) {
                return $i;
            }
        }

        return false;
    }
}
