<?php

declare(strict_types=1);

namespace HelgeSverre\Toon;

use InvalidArgumentException;

final class Primitives
{
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
                // Note: Could check for negative zero with fdiv(1, $value) === -INF
                // but we always return '0' anyway for consistency with JS
                return '0';
            }

            // Expand scientific notation for consistency with JS
            // Check if the value would be displayed in scientific notation
            if (is_float($value) && (abs($value) <= 1e-6 || abs($value) >= 1e20)) {
                // Save current locale
                $oldLocale = setlocale(LC_NUMERIC, '0');
                setlocale(LC_NUMERIC, 'C');

                // For very large numbers
                if (abs($value) >= 1) {
                    $result = sprintf('%.0f', $value);
                } else {
                    // For very small numbers
                    $result = rtrim(rtrim(sprintf('%.10f', $value), '0'), '.');
                }

                // Restore locale
                if ($oldLocale !== false) {
                    setlocale(LC_NUMERIC, $oldLocale);
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

    public static function encodeStringLiteral(string $value, string $delimiter, bool $isKey = false): string
    {
        if (self::isSafeUnquoted($value, $delimiter, $isKey)) {
            return $value;
        }

        return Constants::DOUBLE_QUOTE.self::escapeString($value).Constants::DOUBLE_QUOTE;
    }

    public static function escapeString(string $value): string
    {
        $escaped = str_replace(Constants::BACKSLASH, Constants::BACKSLASH.Constants::BACKSLASH, $value);
        $escaped = str_replace(Constants::DOUBLE_QUOTE, Constants::BACKSLASH.Constants::DOUBLE_QUOTE, $escaped);
        $escaped = str_replace("\n", '\n', $escaped);
        $escaped = str_replace("\r", '\r', $escaped);
        $escaped = str_replace("\t", '\t', $escaped);

        return $escaped;
    }

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

        // Keywords need quoting
        if (strtolower($value) === Constants::TRUE_LITERAL || strtolower($value) === Constants::FALSE_LITERAL || strtolower($value) === Constants::NULL_LITERAL) {
            return false;
        }

        // Numeric patterns need quoting (including octal-like values)
        if (is_numeric($value) || preg_match('/^-?\d+(\.\d+)?([eE][+-]?\d+)?$/', $value) || preg_match('/^0[0-7]+$/', $value)) {
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
