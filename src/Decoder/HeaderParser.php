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
                $result['length'] = (int) substr($line, $numStart, $pos - $numStart);
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

            // Use DelimiterParser to split fields by active delimiter
            $result['fields'] = DelimiterParser::split($fieldsStr, $result['delimiter'], 0);

            // Clean field names
            $cleanFields = [];
            foreach ($result['fields'] as $field) {
                $field = trim($field);
                if (str_starts_with($field, '"') && str_ends_with($field, '"')) {
                    $field = substr($field, 1, -1);
                }
                $cleanFields[] = $field;
            }
            $result['fields'] = $cleanFields;
            $result['format'] = 'tabular';

            $pos = $braceEnd + 1; // move past }
        } elseif ($result['length'] === null && $line[$pos] === '{') {
            // Standalone {fields}: format (tabular continuation)
            return self::parseDirectArrayHeader('[0]'.substr($line, $pos));
        }

        // Expect : after header
        if ($pos >= $len || $line[$pos] !== ':') {
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
