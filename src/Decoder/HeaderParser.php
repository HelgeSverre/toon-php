<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Decoder;

use HelgeSverre\Toon\Exceptions\SyntaxException;

/**
 * Parses TOON array headers using character-by-character state machine.
 *
 * Following TOON Spec v2.0 Appendix B.2: Array Header Parsing
 *
 * Handles all header formats:
 * - [N]: or [N]: values (inline array)
 * - [N|]: or [N\t]: (with delimiter)
 * - [N]{fields}: (tabular)
 * - {fields}: (tabular continuation)
 * - key[N]: (keyed arrays)
 *
 * Note: [#N] format (with length marker) was removed in TOON v2.0 and will be rejected.
 */
final class HeaderParser
{
    /**
     * Parse a TOON array header.
     *
     * Returns null if line doesn't contain a valid array header.
     *
     * @return array{key: ?string, length: ?int, delimiter: string, fields: ?array<string>, inlineValues: ?string, format: string}|null
     */
    public static function parseHeader(string $line): ?array
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        // Check if this is a key-value line with array header: key[N]: value
        $colonPos = strpos($line, ':');
        if ($colonPos !== false) {
            $beforeColon = substr($line, 0, $colonPos);
            $afterColon = substr($line, $colonPos + 1);

            // Check if beforeColon contains an array header
            if (strpos($beforeColon, '[') !== false || strpos($beforeColon, '{') !== false) {
                /** @var array{key: ?string, length: ?int, delimiter: string, fields: ?array<string>, inlineValues: ?string, format: string}|null */
                return self::parseKeyedArrayHeader($beforeColon, $afterColon);
            }
        }

        // Direct array header (no key)
        if (str_starts_with($line, '[') || str_starts_with($line, '{')) {
            /** @var array{key: ?string, length: ?int, delimiter: string, fields: ?array<string>, inlineValues: ?string, format: string}|null */
            return self::parseDirectArrayHeader($line);
        }

        return null;
    }

    /**
     * @return array{key: ?string, length: ?int, delimiter: string, fields: ?array<string>, inlineValues: ?string, format: string}|null
     */
    private static function parseKeyedArrayHeader(string $keyPart, string $valuePart): ?array
    {
        // Find where the array header starts in the key
        $bracketPos = strpos($keyPart, '[');
        $bracePos = strpos($keyPart, '{');

        $headerStart = false;
        if ($bracketPos !== false && ($bracePos === false || $bracketPos < $bracePos)) {
            $headerStart = $bracketPos;
        } elseif ($bracePos !== false) {
            $headerStart = $bracePos;
        }

        if ($headerStart === false) {
            return null;
        }

        $key = trim(substr($keyPart, 0, $headerStart));

        // A quoted key prefix (e.g. "my-key"[3]:) MUST be unescaped per §7.4.
        if ($key !== '' && str_starts_with($key, '"')) {
            $key = ValueParser::parseKey($key, 0);
        }

        $headerAndValue = substr($keyPart, $headerStart).':'.$valuePart;

        $header = self::parseDirectArrayHeader($headerAndValue);
        if ($header === null) {
            return null;
        }

        $header['key'] = $key;

        return $header;
    }

    /**
     * @return array{key: ?string, length: ?int, delimiter: string, fields: ?array<string>, inlineValues: ?string, format: string}|null
     */
    private static function parseDirectArrayHeader(string $line): ?array
    {
        $result = [
            'key' => null,
            'length' => null,
            'delimiter' => ',',
            'fields' => null,
            'inlineValues' => null,
            'format' => 'list',
        ];

        $pos = 0;
        $len = strlen($line);

        // Parse length section [N] or [N|] etc
        if ($line[$pos] === '[') {
            $pos++; // skip [

            // TOON v2.0: Reject deprecated [#N] format
            if ($pos < $len && $line[$pos] === '#') {
                throw new SyntaxException(
                    'Invalid array header format: [#N] syntax is not supported in TOON v2.0. Use [N] instead.',
                    0,
                    $line
                );
            }

            // Parse length number
            $numStart = $pos;
            while ($pos < $len && ctype_digit($line[$pos])) {
                $pos++;
            }

            if ($pos > $numStart) {
                $numStr = substr($line, $numStart, $pos - $numStart);

                // Reject leading-zero lengths like [03] (§6): these MUST NOT be
                // interpreted as bracket segments. Returning null lets non-strict
                // callers fall through to key-value parsing; strict mode errors
                // on the resulting bracket-bearing key (see Parser).
                if (strlen($numStr) > 1 && $numStr[0] === '0') {
                    return null;
                }

                $result['length'] = (int) $numStr;
            }

            // TOON v2.0: Also check for # after digits (catches [N#] pattern)
            if ($pos < $len && $line[$pos] === '#') {
                throw new SyntaxException(
                    'Invalid array header format: [#N] syntax is not supported in TOON v2.0. Use [N] instead.',
                    0,
                    $line
                );
            }

            // Check for delimiter marker
            if ($pos < $len) {
                if ($line[$pos] === '|') {
                    $result['delimiter'] = '|';
                    $pos++;
                } elseif ($line[$pos] === "\t") {
                    $result['delimiter'] = "\t";
                    $pos++;
                }
            }

            // Expect ]
            if ($pos >= $len || $line[$pos] !== ']') {
                return null;
            }
            $pos++; // skip ]

            // "[]" (or "[|]", "[\t]") with no digits is the canonical empty
            // array (§9.1). Legacy "[0]" is handled by the digit parse above.
            if ($result['length'] === null) {
                $result['length'] = 0;
            }
        }

        // Parse fields section {field1,field2}
        if ($pos < $len && $line[$pos] === '{') {
            $braceStart = $pos;
            $pos++; // skip {

            // Find matching }
            $braceEnd = strpos($line, '}', $pos);
            if ($braceEnd === false) {
                return null;
            }

            $fieldsStr = substr($line, $pos, $braceEnd - $pos);

            // Split the field list using the delimiter declared in the bracket segment.
            $rawFields = DelimiterParser::split($fieldsStr, $result['delimiter'], 0);

            // Clean field names, detecting a header delimiter mismatch (§6, §14.2):
            // the field list MUST use the same active delimiter as the bracket segment.
            // If an unquoted field still contains a different delimiter character, the
            // two segments disagree, so this is not a valid header. Returning null lets
            // the caller reject it in strict mode (malformed bracket key) or fall through
            // to key-value parsing in non-strict mode.
            $cleanFields = [];
            foreach ($rawFields as $field) {
                $field = trim($field);
                $isQuoted = strlen($field) >= 2 && str_starts_with($field, '"') && str_ends_with($field, '"');

                if (! $isQuoted) {
                    foreach ([',', '|', "\t"] as $otherDelimiter) {
                        if ($otherDelimiter !== $result['delimiter'] && str_contains($field, $otherDelimiter)) {
                            return null;
                        }
                    }
                } else {
                    $field = substr($field, 1, -1);
                }

                $cleanFields[] = $field;
            }
            $result['fields'] = $cleanFields;
            $result['format'] = 'tabular';

            $pos = $braceEnd + 1; // move past }
        } elseif ($result['length'] === null && $pos < $len && $line[$pos] === '{') {
            // Standalone {fields}: format (tabular continuation)
            return self::parseDirectArrayHeader('[0]'.substr($line, $pos));
        }

        // Expect : after header
        if ($pos >= $len || $line[$pos] !== ':') {
            // Bare "[]" literal on its own — root/standalone empty array (§5, §9.1).
            if ($pos >= $len && $result['length'] === 0 && $result['fields'] === null) {
                $result['format'] = 'inline';

                return $result;
            }

            return null;
        }
        $pos++; // skip :

        // Check if there are inline values after :
        $remaining = trim(substr($line, $pos));
        if ($remaining !== '') {
            $result['inlineValues'] = $remaining;
            $result['format'] = 'inline';
        } elseif ($result['fields'] !== null) {
            $result['format'] = 'tabular';
        } else {
            // Empty after colon: [N]:
            // If length is 0, it's an empty inline array
            // Otherwise, it's a list array that expects child lines
            $result['format'] = ($result['length'] === 0) ? 'inline' : 'list';
        }

        return $result;
    }
}
