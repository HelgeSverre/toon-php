<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Decoder;

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
        // Trim whitespace (§12.11 - whitespace tolerance)
        $token = trim($token);

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
        // Must start and end with quotes
        if (! str_starts_with($token, '"') || ! str_ends_with($token, '"')) {
            throw new SyntaxException(
                'Unterminated quoted string',
                $lineNumber,
                $token
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

            if ($char === '\\' && $i + 1 < $len) {
                $next = $str[$i + 1];

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
                        $original
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
     * Try to parse a token as a number.
     *
     * Returns null if token is not a valid number.
     * Handles leading zero rule (§4.3): "05" is a string, not a number.
     *
     * @param  string  $token  Token to parse
     * @return int|float|null Parsed number, or null if not a number
     */
    private static function parseNumeric(string $token): int|float|null
    {
        // Check for leading zero (except "0" itself) (§4.3)
        if (self::hasLeadingZero($token)) {
            return null; // Treat as string
        }

        // Try integer parsing
        if (is_numeric($token) && ! str_contains($token, '.') && ! str_contains($token, 'e') && ! str_contains($token, 'E')) {
            $value = (int) $token;

            // Verify the string representation matches (handles overflow)
            if ((string) $value === $token) {
                return $value;
            }

            // Overflow: convert to float
            return (float) $token;
        }

        // Try float parsing (handles scientific notation)
        if (is_numeric($token)) {
            return (float) $token;
        }

        // Not a number
        return null;
    }

    /**
     * Check if a token has a forbidden leading zero.
     *
     * Per §4.3: "05", "0001" are strings, but "0", "0.5", "0e10" are numbers.
     *
     * @param  string  $token  Token to check
     * @return bool True if token has leading zero and should be treated as string
     */
    private static function hasLeadingZero(string $token): bool
    {
        // Must start with '0'
        if (! str_starts_with($token, '0')) {
            return false;
        }

        // "0" alone is valid
        if ($token === '0') {
            return false;
        }

        // "0." (decimal) is valid
        if (str_starts_with($token, '0.')) {
            return false;
        }

        // "0e" or "0E" (scientific notation) is valid
        if (str_starts_with($token, '0e') || str_starts_with($token, '0E')) {
            return false;
        }

        // Otherwise: "05", "0001", etc. are invalid numbers
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
        $token = trim($token);

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
