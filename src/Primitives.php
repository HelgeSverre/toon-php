<?php

declare(strict_types=1);

namespace HelgeSverre\Toon;

use InvalidArgumentException;

final class Primitives
{
    /**
     * Encode a primitive value (null, bool, int, float, string) to TOON format.
     *
     * Numbers are encoded in canonical decimal form when n = 0 or
     * 1e-6 <= |n| < 1e21 (§2). Finite numbers outside that range use exponent
     * notation with a lowercase "e" and explicit sign. Negative zero is
     * normalized to zero (§2). Sufficient precision is maintained for
     * round-trip fidelity so decode(encode(x)) equals x (§2).
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
                $abs = abs($value);

                // Finite numbers outside the canonical decimal range
                // (non-zero |n| < 1e-6, or |n| >= 1e21) use exponent notation (§2).
                if ($abs >= 1e21 || ($abs !== 0.0 && $abs < 1e-6)) {
                    return self::formatExponential($value);
                }

                // Use json_encode for locale-independent formatting
                $result = json_encode($value);
                if ($result === false) {
                    throw new InvalidArgumentException('Failed to encode float value');
                }

                // If result contains scientific notation, expand it to canonical decimal
                if (stripos($result, 'e') !== false) {
                    if ($abs >= 1) {
                        // Large numbers: use integer format if whole number
                        if ($value == floor($value)) {
                            // Use number_format for locale-independent formatting
                            $result = number_format($value, 0, '.', '');
                        } else {
                            // Use fixed-point with sufficient precision, then trim trailing zeros
                            // number_format is locale-independent when decimal separator is explicit
                            $result = rtrim(rtrim(number_format($value, 14, '.', ''), '0'), '.');
                        }
                    } else {
                        // Small numbers: use fixed-point and trim trailing zeros
                        // number_format is locale-independent when decimal separator is explicit
                        $result = rtrim(rtrim(number_format($value, 20, '.', ''), '0'), '.');
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
     * Format a float outside the canonical decimal range in exponent notation (§2).
     *
     * Uses the shortest round-trippable mantissa, a lowercase "e", and an explicit
     * exponent sign (e.g. 1e+21, 1e-7) for byte-for-byte determinism.
     *
     * @param  float  $value  The value to format (|value| >= 1e21 or 0 < |value| < 1e-6)
     * @return string The exponent-notation representation
     */
    private static function formatExponential(float $value): string
    {
        $encoded = json_encode($value);
        if ($encoded === false) {
            throw new InvalidArgumentException('Failed to encode float value');
        }

        // PHP already emits a round-trippable form; normalize it to lowercase "e"
        // with an explicit exponent sign and no trailing-zero mantissa.
        $encoded = strtolower($encoded);
        if (! str_contains($encoded, 'e')) {
            return $encoded;
        }

        [$mantissa, $exponent] = explode('e', $encoded, 2);
        if (str_contains($mantissa, '.')) {
            $mantissa = rtrim(rtrim($mantissa, '0'), '.');
        }
        if ($exponent === '' || ($exponent[0] !== '+' && $exponent[0] !== '-')) {
            $exponent = '+'.$exponent;
        }

        return $mantissa.'e'.$exponent;
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
     * Per TOON §7.1: backslash, double quote, LF, CR and HTAB use their short
     * escapes (\\, \", \n, \r, \t). All other C0 control characters
     * (U+0000-U+0008, U+000B, U+000C, U+000E-U+001F) are emitted as \uXXXX with
     * lowercase hex. Control characters are preserved as data, never stripped (§15).
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

        // Remaining C0 control characters have no short escape; emit \uXXXX (§7.1).
        $result = preg_replace_callback(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F]/',
            fn (array $m): string => sprintf('\u%04x', ord($m[0])),
            $escaped
        );
        assert($result !== null);

        return $result;
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
