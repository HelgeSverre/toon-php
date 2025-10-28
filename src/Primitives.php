<?php

declare(strict_types=1);

namespace HelgeSverre\Toon;

use InvalidArgumentException;

final class Primitives
{
    /**
     * Encode a primitive value (null, bool, int, float, string) to TOON format.
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
                // Both 0.0 and -0.0 should encode as '0'
                return '0';
            }

            // For floats, expand scientific notation for consistency with JS
            // Use json_encode for locale-independent formatting, then process
            if (is_float($value)) {
                // json_encode gives us locale-independent decimal representation
                $result = json_encode($value);
                if ($result === false) {
                    throw new InvalidArgumentException('Failed to encode float value');
                }

                // If result contains scientific notation, expand it
                if (stripos($result, 'e') !== false) {
                    if (abs($value) >= 1) {
                        // For large numbers, use integer format
                        // Must ensure locale-independent output
                        $result = number_format($value, 0, '.', '');
                    } else {
                        // For small numbers, use fixed-point and trim trailing zeros
                        // Create locale-independent representation
                        $result = rtrim(rtrim(number_format($value, 20, '.', ''), '0'), '.');
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
     * Keys matching the identifier pattern ^[A-Za-z_][\w.]*$ are unquoted.
     * Other keys are quoted and escaped.
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
     * @param  string  $value  The string to escape
     * @return string The escaped string
     */
    public static function escapeString(string $value): string
    {
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
     * Strings need quoting if they: are empty, have leading/trailing whitespace,
     * contain structural characters, match keywords, look numeric, contain control
     * characters, or start with list markers.
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
