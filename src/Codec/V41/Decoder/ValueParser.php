<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Codec\V41\Decoder;

use HelgeSverre\Toon\Exceptions\SyntaxException;

/**
 * Parses primitive values and handles string unescaping.
 *
 * Handles: strings (quoted/unquoted), numbers, booleans, null.
 *
 * @internal Version-specific codec implementation.
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
        // §12: token trimming is exactly U+0020, no other characters.
        $token = trim($token, ' ');

        // Empty string after trimming is invalid
        if ($token === '') {
            throw new SyntaxException('Empty token', $lineNumber);
        }

        // Quoted string
        if (str_starts_with($token, '"')) {
            return self::parseQuotedString($token, $lineNumber);
        }

        // Unquoted primitives
        return self::parsePrimitive($token, $lineNumber);
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
        // §7.4 quoted-token boundary (any mode): a token whose first character is a
        // double quote MUST be a complete quoted token - its closing quote MUST be
        // the token's last character, and any character after it MUST error.
        $closing = self::findClosingQuote($token);

        if ($closing === null) {
            throw new SyntaxException(
                'Unterminated quoted string',
                $lineNumber,
                $token,
            );
        }

        if ($closing !== strlen($token) - 1) {
            throw new SyntaxException(
                'Unexpected content after closing quote',
                $lineNumber,
                $token,
            );
        }

        // Extract content (remove surrounding quotes)
        $content = substr($token, 1, -1);

        // Unescape (§7.1)
        return self::unescape($content, $lineNumber, $token);
    }

    /**
     * Index of the quote closing the quoted token that starts at index 0, or null
     * when the token is unterminated. A backslash escapes the next character (§7.1).
     */
    private static function findClosingQuote(string $token): ?int
    {
        $len = strlen($token);

        for ($i = 1; $i < $len; $i++) {
            $char = $token[$i];

            if ($char === '\\') {
                $i++;

                continue;
            }

            if ($char === '"') {
                return $i;
            }
        }

        return null;
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

            if ($char === '\\' && $i + 1 < $len) {
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

        if (strlen($hex) < 4 || preg_match('/^[0-9a-f]{4}$/iD', $hex) !== 1) {
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
     * Parse an unquoted primitive token.
     *
     * @param  string  $token  Unquoted token
     * @param  int  $lineNumber  Line number for error reporting
     * @return mixed Parsed value (string|int|float|bool|null)
     */
    private static function parsePrimitive(string $token, int $lineNumber): mixed
    {
        // Boolean keywords (case-sensitive)
        if ($token === 'true') {
            return true;
        }

        if ($token === 'false') {
            return false;
        }

        // Null keyword
        if ($token === 'null') {
            return null;
        }

        // Try to parse as number
        $number = self::parseNumeric($token);

        if ($number !== null) {
            return $number;
        }

        // Otherwise it's an unquoted string
        return $token;
    }

    /**
     * TOON's normative decoder number grammar (§4).
     *
     * An unquoted token decodes as a number if and only if it matches this pattern
     * and carries no forbidden leading zeros. Tokens such as ".5", "1.", "+5",
     * "Infinity", "NaN", "0x10" and "1_000" are strings; the decision is never
     * delegated to a host parser with a wider grammar.
     */
    private const NUMBER_GRAMMAR = '/^-?[0-9]+(?:\.[0-9]+)?(?:e[+-]?[0-9]+)?$/i';

    /**
     * Try to parse a token as a number per the §4 number grammar.
     *
     * Returns null if the token is not a number, in which case it decodes as a string.
     *
     * @param  string  $token  Token to parse
     * @return int|float|null Parsed number, or null if not a number
     */
    private static function parseNumeric(string $token): int|float|null
    {
        // The grammar is a subset of is_numeric(); the second check narrows the type.
        if (preg_match(self::NUMBER_GRAMMAR, $token) !== 1 || ! is_numeric($token)) {
            return null;
        }

        // Forbidden leading zeros in the integer part (§4): "05" is a string.
        if (self::hasLeadingZero($token)) {
            return null;
        }

        // Integers stay integers unless they overflow the host's integer domain.
        if (! str_contains($token, '.') && ! str_contains($token, 'e') && ! str_contains($token, 'E')) {
            $value = (int) $token;

            // §4: "-0" decodes to zero.
            if ((string) $value === $token || $token === '-0') {
                return $value;
            }

            // Out of the integer domain: fall back to the float approximation.
            return (float) $token;
        }

        $float = (float) $token;

        // §4: negative zero decodes to zero.
        return $float === 0.0 ? 0.0 : $float;
    }

    /**
     * Check if a token has a forbidden leading zero.
     *
     * Per §2.4: "05", "0001", "-05", "-0001" are strings, but "0", "0.5", "0e10", "-0", "-0.5", "-0e1" are numbers.
     *
     * @param  string  $token  Token to check
     * @return bool True if token has leading zero and should be treated as string
     */
    private static function hasLeadingZero(string $token): bool
    {
        // Handle negative numbers
        $checkToken = $token;
        if (str_starts_with($token, '-')) {
            $checkToken = substr($token, 1);
        }

        // Must start with '0'
        if (! str_starts_with($checkToken, '0')) {
            return false;
        }

        // "0" or "-0" alone is valid
        if ($checkToken === '0') {
            return false;
        }

        // "0." or "-0." (decimal) is valid
        if (str_starts_with($checkToken, '0.')) {
            return false;
        }

        // "0e" or "0E" or "-0e" or "-0E" (scientific notation) is valid
        if (str_starts_with($checkToken, '0e') || str_starts_with($checkToken, '0E')) {
            return false;
        }

        // Otherwise: "05", "0001", "-05", "-0001", etc. are invalid numbers
        return true;
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
        // §12: key tokens are trimmed of U+0020 only.
        $token = trim($token, ' ');

        if ($token === '') {
            throw new SyntaxException('Empty key', $lineNumber);
        }

        // Quoted key
        if (str_starts_with($token, '"')) {
            return self::parseQuotedString($token, $lineNumber);
        }

        // Unquoted identifier
        return $token;
    }
}
