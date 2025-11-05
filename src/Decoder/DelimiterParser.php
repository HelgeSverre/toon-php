<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Decoder;

use HelgeSverre\Toon\Exceptions\SyntaxException;

final class DelimiterParser
{
    /**
     * @return array<int, string>
     */
    public static function split(string $input, string $delimiter = ',', int $lineNumber = 0): array
    {
        $input = trim($input);
        if ($input === '') {
            return [];
        }

        $values = [];
        $current = '';
        $inQuotes = false;
        $escaped = false;
        $len = strlen($input);

        for ($i = 0; $i < $len; $i++) {
            $char = $input[$i];
            if ($escaped) {
                $current .= $char;
                $escaped = false;

                continue;
            }
            if ($char === '\\') {
                $current .= $char;
                $escaped = true;

                continue;
            }
            if ($char === '"') {
                $inQuotes = ! $inQuotes;
                $current .= $char;

                continue;
            }
            if ($char === $delimiter && ! $inQuotes) {
                $values[] = self::trimUnquoted($current);
                $current = '';

                continue;
            }
            $current .= $char;
        }

        if ($inQuotes) {
            throw new SyntaxException('Unterminated quoted string', $lineNumber, substr($input, 0, 50));
        }

        $values[] = self::trimUnquoted($current);

        return $values;
    }

    private static function trimUnquoted(string $value): string
    {
        $trimmed = trim($value);
        if (strlen($trimmed) >= 2 && $trimmed[0] === '"' && substr($trimmed, -1) === '"') {
            return $trimmed;
        }

        return $trimmed;
    }

    public static function isArrayHeader(string $line): bool
    {
        $line = ltrim($line);

        return (strpos($line, '[') === 0) || (strpos($line, '{') === 0);
    }

    public static function detectArrayFormat(string $line): ?string
    {
        $line = trim($line);
        if (! self::isArrayHeader($line)) {
            return null;
        }
        if (str_contains($line, '{')) {
            return 'tabular';
        }
        if (preg_match('/^\[#?\d+[|\t]?\]:\s*.+/', $line)) {
            return 'inline';
        }

        return 'list';
    }

    public static function extractDelimiter(string $header): string
    {
        $header = str_replace('#', '', $header);
        if (str_contains($header, '|')) {
            return '|';
        }
        if (str_contains($header, "\t")) {
            return "\t";
        }

        return ',';
    }

    /**
     * @return array<int, string>
     */
    public static function extractFields(string $header, int $lineNumber = 0): array
    {
        $header = trim($header);
        if (! str_starts_with($header, '{') || ! str_ends_with($header, '}')) {
            throw new SyntaxException('Invalid tabular array header format', $lineNumber, $header);
        }
        $content = substr($header, 1, -1);
        if ($content === '') {
            throw new SyntaxException('Empty tabular array header', $lineNumber, $header);
        }
        $fields = self::split($content, ',', $lineNumber);
        $cleanFields = [];
        foreach ($fields as $field) {
            $field = trim($field);
            if ($field === '') {
                throw new SyntaxException('Empty field name in tabular array header', $lineNumber, $header);
            }
            if (str_starts_with($field, '"') && str_ends_with($field, '"')) {
                $field = substr($field, 1, -1);
            }
            $cleanFields[] = $field;
        }

        return $cleanFields;
    }

    public static function extractLength(string $header, int $lineNumber = 0): int
    {
        $header = trim($header);
        if (! str_starts_with($header, '[') || ! str_ends_with($header, ']')) {
            throw new SyntaxException('Invalid array header format: must be [N] or [#N]', $lineNumber, $header);
        }
        $content = trim(substr($header, 1, -1));
        if ($content === '') {
            throw new SyntaxException('Empty array header: must contain a length', $lineNumber, $header);
        }
        if (str_starts_with($content, '#')) {
            $content = trim(substr($content, 1));
        }
        if (! ctype_digit($content) || $content === '0') {
            throw new SyntaxException('Invalid array length: must be a positive integer', $lineNumber, $header);
        }

        return (int) $content;
    }
}
