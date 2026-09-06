<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Codec\V41\Decoder;

use HelgeSverre\Toon\Exceptions\SyntaxException;

/** @internal Version-specific codec implementation. */
final class DelimiterParser
{
    /**
     * @return array<int, string>
     */
    public static function split(string $input, string $delimiter = ',', int $lineNumber = 0): array
    {
        // §12: token trimming is exactly U+0020. Any other whitespace - notably
        // HTAB outside its delimiter role - is part of the token.
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
            // Escapes only exist inside quoted strings/keys (§7.1). An unquoted
            // backslash is a literal character and MUST NOT escape the delimiter.
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
        // §12: exactly U+0020, no other characters.
        return trim($value, ' ');
    }

    public static function isArrayHeader(string $line): bool
    {
        $line = ltrim($line, ' ');

        return (strpos($line, '[') === 0) || (strpos($line, '{') === 0);
    }
}
