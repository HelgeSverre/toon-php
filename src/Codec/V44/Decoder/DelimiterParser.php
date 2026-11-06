<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Codec\V44\Decoder;

use HelgeSverre\Toon\Exceptions\SyntaxException;

final class DelimiterParser
{
    /**
     * Split a line on the delimiter, leaving delimiters inside quotes alone.
     * Tokens keep their quotes and lose surrounding spaces.
     *
     * @return list<string>
     */
    public static function split(string $input, string $delimiter = ',', int $lineNumber = 0): array
    {
        $input = trim($input, ' ');
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
            // Escapes only exist inside quotes, an unquoted backslash is just a character
            if ($char === '\\' && $inQuotes) {
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
                $values[] = trim($current, ' ');
                $current = '';

                continue;
            }
            $current .= $char;
        }

        // A quote in the middle of a token may run to the end of the line, but a
        // token that starts with a quote has to close it
        if ($inQuotes && str_starts_with(trim($current, ' '), '"')) {
            throw new SyntaxException('Unterminated quoted string', $lineNumber, substr($input, 0, 50));
        }

        $values[] = trim($current, ' ');

        return $values;
    }

    /**
     * Position of the first occurrence of $char outside quotes, or null.
     * A quote opens a span that runs to the next unescaped quote or the end of the line.
     */
    public static function findUnquoted(string $input, string $char): ?int
    {
        $inQuotes = false;
        $len = strlen($input);

        for ($i = 0; $i < $len; $i++) {
            $current = $input[$i];
            if ($inQuotes) {
                if ($current === '\\') {
                    $i++;
                } elseif ($current === '"') {
                    $inQuotes = false;
                }

                continue;
            }
            if ($current === '"') {
                $inQuotes = true;

                continue;
            }
            if ($current === $char) {
                return $i;
            }
        }

        return null;
    }
}
