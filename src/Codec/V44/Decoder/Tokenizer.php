<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Codec\V44\Decoder;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\Exceptions\IndentationException;
use HelgeSverre\Toon\Exceptions\SyntaxException;

/**
 * Splits TOON input into lines, drops comments and works out each line's depth.
 */
final class Tokenizer
{
    /**
     * @return list<Line>
     */
    public static function tokenize(string $input, DecodeOptions $options): array
    {
        if (preg_match('//u', $input) !== 1) {
            throw new SyntaxException('Input is not valid UTF-8');
        }

        // A byte-order mark at the very start is not content
        if (str_starts_with($input, "\u{FEFF}")) {
            $input = substr($input, 3);
        }

        $lines = [];
        foreach (explode("\n", $input) as $index => $raw) {
            $number = $index + 1;
            if (str_ends_with($raw, "\r")) {
                $raw = substr($raw, 0, -1);
            }
            $raw = rtrim($raw, ' ');

            $indentation = substr($raw, 0, strspn($raw, " \t"));
            $content = substr($raw, strlen($indentation));

            // Comment lines vanish completely, but only spaces may come before the #
            if (str_starts_with($content, '#') && ! str_contains($indentation, "\t")) {
                continue;
            }

            // Non-strict mode also treats a line of only spaces and tabs as blank
            if ($raw === '' || ($content === '' && ! $options->strict)) {
                $lines[] = new Line($number, 0, '', true);

                continue;
            }

            $lines[] = new Line($number, self::depth($indentation, $options, $number, $raw), $content, false);
        }

        return $lines;
    }

    private static function depth(string $indentation, DecodeOptions $options, int $number, string $raw): int
    {
        $tabs = substr_count($indentation, "\t");
        $spaces = strlen($indentation) - $tabs;

        if (! $options->strict) {
            return $tabs + intdiv($spaces, $options->indent);
        }

        if ($tabs > 0) {
            throw new IndentationException('Tabs not allowed in indentation (strict mode)', $number, $raw);
        }

        if ($spaces % $options->indent !== 0) {
            throw new IndentationException("Indentation must be multiple of {$options->indent} (got {$spaces} spaces)", $number, $raw);
        }

        return intdiv($spaces, $options->indent);
    }
}
