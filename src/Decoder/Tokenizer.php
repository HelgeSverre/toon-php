<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Decoder;

use HelgeSverre\Toon\DecodeOptions;

/**
 * Tokenizes TOON input into lines with metadata.
 *
 * Handles line splitting, indentation depth computation, and validation.
 */
final class Tokenizer
{
    /**
     * Tokenize TOON input into lines with metadata.
     *
     * @param  string  $input  TOON input string
     * @param  DecodeOptions  $options  Decode options
     * @return array<int, array{content: string, depth: int, line: int, indent: int, blank: bool}> Array of line metadata
     */
    public static function tokenize(string $input, DecodeOptions $options): array
    {
        // Preprocess input (handle trailing newlines)
        $input = self::preprocessInput($input);

        // Split into lines
        $rawLines = explode("\n", $input);

        $lines = [];
        $lineNumber = 1;

        foreach ($rawLines as $line) {
            // Track blank lines (needed for strict mode validation in arrays)
            if (trim($line) === '') {
                $lines[] = [
                    'content' => '',
                    'depth' => 0,
                    'line' => $lineNumber,
                    'indent' => 0,
                    'blank' => true,
                ];
                $lineNumber++;

                continue;
            }

            // Compute indentation
            $indent = self::getIndentation($line);

            // Validate indentation in strict mode
            if ($options->strict) {
                StrictValidator::validateNoTabIndentation($line, $lineNumber);
                StrictValidator::validateIndentationMultiple($indent, $options->indent, $lineNumber, $line);
            } else {
                // In lenient mode, check for tabs but don't error (per §12.10)
                // Current implementation: reject tabs in both modes (implementation-defined)
                // Comment this out to allow tabs in lenient mode
                // StrictValidator::validateNoTabIndentation($line, $lineNumber);
            }

            // Compute depth
            $depth = self::computeDepth($indent, $options->indent, $options->strict);

            $lines[] = [
                'content' => $line,
                'depth' => $depth,
                'line' => $lineNumber,
                'indent' => $indent,
                'blank' => false,
            ];

            $lineNumber++;
        }

        return $lines;
    }

    /**
     * Preprocess input string.
     *
     * Handles trailing newlines per spec §12.15 (decoders SHOULD accept trailing newline).
     *
     * @param  string  $input  Raw input
     * @return string Preprocessed input
     */
    private static function preprocessInput(string $input): string
    {
        // Accept optional trailing newline (spec §12.15)
        return rtrim($input, "\n");
    }

    /**
     * Get the number of leading spaces in a line.
     *
     * @param  string  $line  Line to analyze
     * @return int Number of leading spaces
     */
    private static function getIndentation(string $line): int
    {
        // Count leading spaces
        $trimmed = ltrim($line, ' ');

        return strlen($line) - strlen($trimmed);
    }

    /**
     * Compute nesting depth from indentation.
     *
     * In strict mode: depth = indent / indentSize (must be exact multiple)
     * In non-strict mode: depth = floor(indent / indentSize)
     *
     * @param  int  $indent  Number of leading spaces
     * @param  int  $indentSize  Expected spaces per level
     * @param  bool  $strict  Strict mode flag
     * @return int Nesting depth (0 = root level)
     */
    private static function computeDepth(int $indent, int $indentSize, bool $strict): int
    {
        // Handle zero indent size (compact mode)
        if ($indentSize === 0) {
            return 0;
        }

        if ($strict) {
            // Strict mode: exact multiple required
            return (int) ($indent / $indentSize);
        }

        // Non-strict mode: floor division
        return (int) floor($indent / $indentSize);
    }
}
