<?php

declare(strict_types=1);

namespace HelgeSverre\Toon;

use InvalidArgumentException;

final class Primitives
{
    /**
     * Encode a primitive value (null, bool, int, float, string) to TOON format.
     *
     * Numbers are encoded without exponential notation (§2). Negative zero is
     * normalized to zero (§2). Very large numbers (>10^20) are quoted as strings
     * for precision preservation (§2.9). Sufficient precision is maintained for
     * round-trip fidelity (§2.7).
     *
     * @param  mixed  $value  The primitive value to encode
     * @param  string  $delimiter  The delimiter used in the context (affects string quoting)
     * @return string The encoded primitive value
     *
     * @throws InvalidArgumentException If the value is not a supported primitive type
     */
    public static function encodePrimitive(mixed $value, string $delimiter): string
    {
        if ($value === null) {
            return Constants::NULL_LITERAL;
        }

        if (is_bool($value)) {
            return $value ? Constants::TRUE_LITERAL : Constants::FALSE_LITERAL;
        }

        if (is_int($value) || is_float($value)) {
            // Handle integer zero
            if (is_int($value) && $value === 0) {
                return '0';
            }

            // Handle float zero and negative zero
            if (is_float($value) && $value === 0.0) {
                return '0';
            }

            // Expand scientific notation for floats
            if (is_float($value)) {
                // Very large numbers are quoted as strings for exact precision
                if (abs($value) > 1e20) {
                    $str = sprintf('%.0f', $value);

                    return Constants::DOUBLE_QUOTE.$str.Constants::DOUBLE_QUOTE;
                }

                // Use json_encode for locale-independent formatting
                $result = json_encode($value);
                if ($result === false) {
                    throw new InvalidArgumentException('Failed to encode float value');
                }

                // If result contains scientific notation, expand it
                if (stripos($result, 'e') !== false) {
                    if (abs($value) >= 1) {
                        // Large numbers: use integer format if whole number
                        if ($value == floor($value)) {
                            $result = sprintf('%.0f', $value);
                        } else {
                            // Use fixed-point with sufficient precision, then trim trailing zeros
                            $result = rtrim(rtrim(sprintf('%.14f', $value), '0'), '.');
                        }
                    } else {
                        // Small numbers: use fixed-point and trim trailing zeros
                        $result = rtrim(rtrim(sprintf('%.20f', $value), '0'), '.');
                        if ($result === '' || $result === '-') {
                            $result = '0';
                        }
                    }
                }

                return $result;
            }

            return (string) $value;
        }

        if (is_string($value)) {
            return self::encodeStringLiteral($value, $delimiter);
        }

        throw new InvalidArgumentException('Unsupported primitive type');
    }

    /**
     * Encode an object key for TOON format.
     *
     * Keys matching the identifier pattern ^[A-Za-z_][\w.]*$ are unquoted (§7.3).
     * Other keys are quoted and escaped. This applies to both object keys and
     * tabular field names in array headers.
     *
     * @param  string  $key  The key to encode
     * @return string The encoded key (quoted or unquoted)
     */
    public static function encodeKey(string $key): string
    {
        // Keys are unquoted if they match the identifier pattern: ^[A-Za-z_][\w.]*$
        if (preg_match('/^[A-Za-z_][\w.]*$/', $key)) {
            return $key;
        }

        return Constants::DOUBLE_QUOTE.self::escapeString($key).Constants::DOUBLE_QUOTE;
    }

    /**
     * Encode a string literal, determining if quoting is needed.
     *
     * @param  string  $value  The string value to encode
     * @param  string  $delimiter  The delimiter used in the context (affects quoting decisions)
     * @param  bool  $isKey  Whether this string is an object key (stricter quoting rules)
     * @return string The encoded string (quoted or unquoted)
     */
    public static function encodeStringLiteral(string $value, string $delimiter, bool $isKey = false): string
    {
        if (self::isSafeUnquoted($value, $delimiter, $isKey)) {
            return $value;
        }

        return Constants::DOUBLE_QUOTE.self::escapeString($value).Constants::DOUBLE_QUOTE;
    }

    /**
     * Escape special characters in a string for use in quoted strings.
     *
     * Escapes backslashes, double quotes, newlines, carriage returns, and tabs.
     *
     * Per TOON v2.0 §7.1: Only \n, \r, and \t control characters are supported.
     * Strings containing other control characters (0x00-0x08, 0x0B, 0x0C, 0x0E-0x1F)
     * are rejected as the spec provides no escape sequences for them.
     *
     * @param  string  $value  The string to escape
     * @return string The escaped string
     *
     * @throws InvalidArgumentException If string contains unsupported control characters
     */
    public static function escapeString(string $value): string
    {
        // Check for unsupported control characters (§7.1)
        // Allowed: \n (0x0A), \r (0x0D), \t (0x09)
        // Rejected: All other control chars (0x00-0x08, 0x0B, 0x0C, 0x0E-0x1F)
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
            throw new InvalidArgumentException(
                'String contains unsupported control characters. '.
                'Only \\n (newline), \\r (carriage return), and \\t (tab) are allowed per TOON Spec §7.1.'
            );
        }

        $escaped = str_replace(Constants::BACKSLASH, Constants::BACKSLASH.Constants::BACKSLASH, $value);
        $escaped = str_replace(Constants::DOUBLE_QUOTE, Constants::BACKSLASH.Constants::DOUBLE_QUOTE, $escaped);
        $escaped = str_replace("\n", '\n', $escaped);
        $escaped = str_replace("\r", '\r', $escaped);
        $escaped = str_replace("\t", '\t', $escaped);

        return $escaped;
    }

    /**
     * Check if a string can be safely represented without quotes.
     *
     * Strings need quoting if they (§7.2): are empty, have leading/trailing whitespace,
     * contain structural characters, match keywords (true/false/null), look numeric,
     * contain control characters, contain the delimiter, or start with hyphens.
     *
     * @param  string  $value  The string to check
     * @param  string  $delimiter  The delimiter used in the context
     * @param  bool  $isKey  Whether this string is an object key (stricter rules)
     * @return bool True if the string can be unquoted, false if it needs quotes
     */
    public static function isSafeUnquoted(string $value, string $delimiter, bool $isKey = false): bool
    {
        // Empty strings need quoting
        if ($value === '') {
            return false;
        }

        // Strings with leading or trailing whitespace need quoting
        if (trim($value) !== $value) {
            return false;
        }

        // Keys with internal spaces need quoting (but values with spaces are ok)
        if ($isKey && str_contains($value, Constants::SPACE)) {
            return false;
        }

        // Keywords need quoting (case-sensitive)
        if ($value === Constants::TRUE_LITERAL || $value === Constants::FALSE_LITERAL || $value === Constants::NULL_LITERAL) {
            return false;
        }

        // Numeric patterns need quoting (including octal, hex, and binary patterns)
        if (is_numeric($value) || preg_match('/^-?\d+(\.\d+)?([eE][+-]?\d+)?$/', $value) || preg_match('/^0[0-7]+$/', $value)) {
            return false;
        }

        // Hex and binary patterns need quoting
        if (preg_match('/^(?:0x[0-9A-Fa-f]+|0b[01]+)$/', $value)) {
            return false;
        }

        // Check for structural characters
        $structuralChars = [
            Constants::COLON,
            Constants::OPEN_BRACKET,
            Constants::CLOSE_BRACKET,
            Constants::OPEN_BRACE,
            Constants::CLOSE_BRACE,
            Constants::DOUBLE_QUOTE,
            Constants::BACKSLASH,
            $delimiter,
        ];

        foreach ($structuralChars as $char) {
            if (str_contains($value, $char)) {
                return false;
            }
        }

        // Check for control characters
        if (preg_match('/[\x00-\x1F]/', $value)) {
            return false;
        }

        // Strings starting with hyphens (list markers) need quoting
        if (str_starts_with($value, Constants::LIST_ITEM_MARKER)) {
            return false;
        }

        return true;
    }

    private function __construct()
    {
        // Prevent instantiation
    }
}
