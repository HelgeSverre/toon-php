<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Codec\V44\Decoder;

use HelgeSverre\Toon\Exceptions\SyntaxException;

/**
 * Parses primitive values and handles string unescaping.
 *
 * Handles: strings (quoted/unquoted), numbers, booleans, null.
 */
final class ValueParser
{
    /**
     * Parse a token into a PHP value.
     *
     * @param  string  $token  Token to parse
     * @param  int  $lineNumber  Line number for error reporting
     * @return mixed Parsed value (string|int|float|bool|null)
     *
     * @throws SyntaxException If token is invalid
     */
    public static function parseValue(string $token, int $lineNumber = 0): mixed
    {
        $token = trim($token, ' ');

        // An empty token, e.g. between two delimiters, is an empty string
        if ($token === '') {
            return '';
        }

        if (str_starts_with($token, '"')) {
            return self::parseQuotedString($token, $lineNumber);
        }

        return self::parsePrimitive($token);
    }

    /**
     * Parse a quoted string.
     *
     * @param  string  $token  Quoted string token
     * @param  int  $lineNumber  Line number for error reporting
     * @return string Unescaped string
     *
     * @throws SyntaxException If string is invalid
     */
    private static function parseQuotedString(string $token, int $lineNumber): string
    {
        // Must start and end with quotes
        if (strlen($token) < 2 || ! str_ends_with($token, '"')) {
            throw new SyntaxException(
                'Unterminated quoted string',
                $lineNumber,
                $token,
            );
        }

        // Extract content (remove surrounding quotes)
        $content = substr($token, 1, -1);

        // Unescape (§7.1.1, §7.4.1)
        return self::unescape($content, $lineNumber, $token);
    }

    /**
     * Unescape a string.
     *
     * Valid escapes: \\, \", \n, \r, \t
     * Invalid escapes cause errors in strict mode.
     *
     * @param  string  $str  String to unescape
     * @param  int  $lineNumber  Line number for error reporting
     * @param  string  $original  Original token for error context
     * @return string Unescaped string
     *
     * @throws SyntaxException If invalid escape sequence found
     */
    private static function unescape(string $str, int $lineNumber, string $original): string
    {
        $result = '';
        $len = strlen($str);
        $i = 0;

        while ($i < $len) {
            $char = $str[$i];

            // A bare quote inside means the token went on after its closing quote
            if ($char === '"') {
                throw new SyntaxException('Unexpected characters after closing quote', $lineNumber, $original);
            }

            if ($char === '\\' && $i + 1 === $len) {
                throw new SyntaxException('Unterminated quoted string', $lineNumber, $original);
            }

            if ($char === '\\') {
                $next = $str[$i + 1];

                // Unicode escape \uXXXX (§7.1)
                if ($next === 'u') {
                    $result .= self::decodeUnicodeEscape($str, $i, $lineNumber, $original);
                    $i += 6;

                    continue;
                }

                // Valid escapes (§7.1.1)
                $result .= match ($next) {
                    '\\' => '\\',
                    '"' => '"',
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    default => throw new SyntaxException(
                        "Invalid escape sequence: \\{$next}",
                        $lineNumber,
                        $original,
                    ),
                };

                $i += 2;
            } else {
                $result .= $char;
                $i++;
            }
        }

        return $result;
    }

    /**
     * Decode a \uXXXX escape sequence starting at the backslash position (§7.1).
     *
     * Hex digits are case-insensitive; lone surrogates (U+D800-U+DFFF) are
     * rejected; fewer than four hex digits is an error.
     *
     * @param  string  $str  Full (unquoted) string content
     * @param  int  $backslashPos  Index of the backslash beginning the escape
     * @param  int  $lineNumber  Line number for error reporting
     * @param  string  $original  Original token for error context
     * @return string The decoded character as UTF-8
     *
     * @throws SyntaxException If the escape is malformed or a lone surrogate
     */
    private static function decodeUnicodeEscape(string $str, int $backslashPos, int $lineNumber, string $original): string
    {
        $hex = substr($str, $backslashPos + 2, 4);

        if (preg_match('/^[0-9a-f]{4}$/iD', $hex) !== 1) {
            throw new SyntaxException(
                'Invalid escape sequence: \\u requires four hex digits',
                $lineNumber,
                $original,
            );
        }

        $code = (int) hexdec($hex);

        // Lone surrogates cannot be decoded from \uXXXX (§7.1)
        if ($code >= 0xD800 && $code <= 0xDFFF) {
            throw new SyntaxException(
                "Invalid escape sequence: lone surrogate \\u{$hex}",
                $lineNumber,
                $original,
            );
        }

        if ($code <= 0x7F) {
            return chr($code);
        }
        if ($code <= 0x7FF) {
            return chr(0xC0 | ($code >> 6)).chr(0x80 | ($code & 0x3F));
        }

        return chr(0xE0 | ($code >> 12)).chr(0x80 | (($code >> 6) & 0x3F)).chr(0x80 | ($code & 0x3F));
    }

    /**
     * Parse an unquoted token: true, false, null, a number, or else a plain string.
     */
    private static function parsePrimitive(string $token): mixed
    {
        if ($token === 'true') {
            return true;
        }

        if ($token === 'false') {
            return false;
        }

        if ($token === 'null') {
            return null;
        }

        // Only JSON-style numbers count, so 05, +1, .5 and 0x10 stay strings
        if (! is_numeric($token) || ! preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?\z/', $token)) {
            return $token;
        }

        if (strpbrk($token, '.eE') === false) {
            $int = (int) $token;
            if ((string) $int === $token || $token === '-0') {
                return $int;
            }
        }

        // Integers beyond PHP's domain use the float approximation. A token
        // beyond the finite float range stays a string rather than becoming INF.
        $float = (float) $token;
        if (! is_finite($float)) {
            return $token;
        }

        return $float === 0.0 ? 0.0 : $float;
    }

    /**
     * Parse a key token (used for object keys and field names).
     *
     * Keys can be quoted or unquoted identifiers.
     *
     * @param  string  $token  Key token
     * @param  int  $lineNumber  Line number for error reporting
     * @return string Parsed key
     *
     * @throws SyntaxException If key is invalid
     */
    public static function parseKey(string $token, int $lineNumber = 0): string
    {
        $token = trim($token, ' ');

        // Quoted key
        if (str_starts_with($token, '"')) {
            return self::parseQuotedString($token, $lineNumber);
        }

        // Unquoted identifier
        return $token;
    }
}
