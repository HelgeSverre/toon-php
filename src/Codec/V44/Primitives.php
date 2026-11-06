<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Codec\V44;

use HelgeSverre\Toon\Constants;
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

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return self::formatFloat($value);
        }

        if (is_string($value)) {
            return self::encodeStringLiteral($value, $delimiter);
        }

        throw new InvalidArgumentException('Unsupported primitive type');
    }

    /**
     * Format a float with the fewest digits that still read back as the same value.
     *
     * Numbers from 1e-6 up to 1e21 are written as plain decimals (1000000, 0.000001),
     * anything outside that range uses exponent notation (1e+21, 1e-7).
     * Negative zero comes out as 0.
     */
    private static function formatFloat(float $value): string
    {
        if ($value === 0.0) {
            return '0';
        }

        // json_encode already picks the shortest digits that round-trip, e.g. 1.2345678901234568e+20
        // Applications may override serialize_precision. Always request PHP's
        // shortest round-trip conversion and restore the caller's setting.
        $precision = ini_set('serialize_precision', '-1');
        try {
            $json = strtolower(json_encode(abs($value), JSON_THROW_ON_ERROR));
        } finally {
            if ($precision !== false) {
                ini_set('serialize_precision', $precision);
            }
        }
        [$mantissa, $exponent] = array_pad(explode('e', $json), 2, '0');
        [$whole, $fraction] = array_pad(explode('.', $mantissa), 2, '');

        // Work with the bare digits and where the decimal point goes in them
        $digits = $whole.$fraction;
        $point = strlen($whole) + (int) $exponent;
        $trimmed = ltrim($digits, '0');
        $point -= strlen($digits) - strlen($trimmed);
        $digits = rtrim($trimmed, '0');

        $sign = $value < 0 ? '-' : '';
        $abs = abs($value);

        if ($abs < 1e-6 || $abs >= 1e21) {
            $exponent = $point - 1;
            $mantissa = strlen($digits) > 1 ? $digits[0].'.'.substr($digits, 1) : $digits;

            return $sign.$mantissa.'e'.($exponent < 0 ? '-' : '+').abs($exponent);
        }

        if ($point <= 0) {
            return $sign.'0.'.str_repeat('0', -$point).$digits;
        }

        if ($point >= strlen($digits)) {
            return $sign.$digits.str_repeat('0', max(0, $point - strlen($digits)));
        }

        return $sign.substr($digits, 0, $point).'.'.substr($digits, $point);
    }

    /**
     * Encode an object key for TOON format.
     *
     * Keys made of letters, digits, underscores and dots (not starting with a digit
     * or dot) are left unquoted.
     * Other keys are quoted and escaped. This applies to both object keys and
     * tabular field names in array headers.
     *
     * @param  string  $key  The key to encode
     * @return string The encoded key (quoted or unquoted)
     */
    public static function encodeKey(string $key): string
    {
        // \z rather than $, so a key ending in a newline still gets quoted
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_.]*\z/', $key)) {
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
            static fn (array $m): string => sprintf('\u%04x', ord($m[0])),
            $escaped,
        );
        assert($result !== null, 'preg_replace_callback failed on a valid pattern');

        return $result;
    }

    /**
     * Check if a string can be safely represented without quotes.
     *
     * Strings need quoting if they (§7.2): are empty, have leading/trailing whitespace,
     * contain structural characters, match keywords (true/false/null), look numeric,
     * contain control characters, contain the delimiter, or start with "-" or "#".
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

        // Anything that looks like a number, including 05, +1 and 1e-6
        if (preg_match('/^[+-]?[0-9]+(?:\.[0-9]+)?(?:e[+-]?[0-9]+)?$/i', $value)) {
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

        // A leading hyphen reads as a list item, a leading # as a comment line
        if (str_starts_with($value, Constants::LIST_ITEM_MARKER) || str_starts_with($value, Constants::COMMENT_MARKER)) {
            return false;
        }

        return true;
    }

    private function __construct()
    {
        // Prevent instantiation
    }
}
