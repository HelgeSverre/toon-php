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
            // Handle -0 (only for floats, and only if non-zero)
            if (is_float($value) && $value === 0.0) {
                // Check for negative zero
                if (@(1 / $value) === -INF) {
                    return '0';
                }
            }

            // Expand scientific notation for consistency with JS
            if (is_float($value) && $value !== 0.0 && (abs($value) < 1e-6 || abs($value) >= 1e21)) {
                // For very large or very small numbers
                if (abs($value) >= 1) {
                    return sprintf('%.0f', $value);
                } else {
                    return rtrim(rtrim(sprintf('%.10f', $value), '0'), '.');
                }
            }

            return (string) $value;
        }

        if (is_string($value)) {
            return self::encodeStringLiteral($value, $delimiter);
        }

        throw new InvalidArgumentException('Unsupported primitive type');
    }

    public static function encodeStringLiteral(string $value, string $delimiter): string
    {
        if (self::isSafeUnquoted($value, $delimiter)) {
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

    public static function isSafeUnquoted(string $value, string $delimiter): bool
    {
        // Empty strings need quoting
        if ($value === '') {
            return false;
        }

        // Strings with leading or trailing whitespace need quoting
        if (trim($value) !== $value) {
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
