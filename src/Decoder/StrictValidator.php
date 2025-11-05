<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Decoder;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\Exceptions\CountMismatchException;
use HelgeSverre\Toon\Exceptions\IndentationException;
use HelgeSverre\Toon\Exceptions\StrictModeException;
use HelgeSverre\Toon\Exceptions\SyntaxException;

/**
 * Validates TOON input against strict mode requirements.
 *
 * Implements all MUST requirements from TOON Specification §14:
 * - Array count and width mismatches (§14.1-14.4)
 * - Syntax errors (§14.2, §14.5, §14.6)
 * - Indentation errors (§14.3, §14.7, §14.8)
 * - Structural errors (§14.4, §14.9, §14.10)
 *
 * @see docs/SPEC.md Section 14: Strict Mode Errors and Diagnostics
 */
final class StrictValidator
{
    /**
     * Validate empty input (§14.10, REQ-14.10).
     *
     * In strict mode, empty input MUST error.
     * Empty = no non-empty lines after ignoring trailing newlines and blank lines outside arrays.
     *
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines  Tokenized lines
     * @param  DecodeOptions  $options  Decode options
     *
     * @throws StrictModeException If input is empty in strict mode
     */
    public static function validateNotEmpty(array $lines, DecodeOptions $options): void
    {
        if ($options->strict && empty($lines)) {
            throw new StrictModeException('Empty input not allowed in strict mode', 0);
        }
    }

    /**
     * Validate indentation is multiple of indentSize (§14.7, REQ-14.7, §12.7, REQ-12.7).
     *
     * Leading spaces MUST be exact multiple of indentSize; otherwise MUST error in strict mode.
     *
     * @param  int  $indent  Number of leading spaces
     * @param  int  $indentSize  Expected spaces per level
     * @param  int  $lineNumber  Line number for error reporting
     * @param  string  $line  Line content for error context
     *
     * @throws IndentationException If indentation is not exact multiple
     */
    public static function validateIndentationMultiple(
        int $indent,
        int $indentSize,
        int $lineNumber,
        string $line
    ): void {
        if ($indentSize > 0 && $indent % $indentSize !== 0) {
            throw new IndentationException(
                "Indentation must be multiple of {$indentSize} (got {$indent} spaces)",
                $lineNumber,
                $line
            );
        }
    }

    /**
     * Validate no tabs in indentation (§14.8, REQ-14.8, §12.8, REQ-12.8).
     *
     * Tabs MUST NOT be used for indentation in strict mode.
     * Note: Tabs ARE allowed in quoted strings and as HTAB delimiter.
     *
     * @param  string  $line  Line content
     * @param  int  $lineNumber  Line number for error reporting
     *
     * @throws IndentationException If tabs found in indentation
     */
    public static function validateNoTabIndentation(string $line, int $lineNumber): void
    {
        // Check if line starts with tab or has tab in leading whitespace
        if (str_starts_with($line, "\t") || preg_match('/^[ ]*\t/', $line)) {
            throw new IndentationException(
                'Tabs not allowed in indentation (strict mode)',
                $lineNumber,
                $line
            );
        }
    }

    /**
     * Validate array count matches declaration (§14.1-14.3, REQ-14.1, REQ-14.2, REQ-14.3).
     *
     * - §14.1 (REQ-14.1): Inline primitive arrays: decoded value count ≠ declared N → MUST error
     * - §14.2 (REQ-14.2): List arrays: number of list items ≠ declared N → MUST error
     * - §14.3 (REQ-14.3): Tabular arrays: number of rows ≠ declared N → MUST error
     *
     * NOTE: This validation is only for strict mode. In lenient mode, count mismatches are allowed.
     *
     * @param  int  $expected  Expected count from [N] declaration
     * @param  int  $actual  Actual parsed count
     * @param  string  $arrayType  Type: 'inline', 'list', or 'tabular'
     * @param  int  $lineNumber  Line number for error reporting
     * @param  string  $snippet  Content snippet for error context
     * @param  bool  $strict  Whether strict mode is enabled
     *
     * @throws CountMismatchException If counts don't match in strict mode
     */
    public static function validateArrayCount(
        int $expected,
        int $actual,
        string $arrayType,
        int $lineNumber,
        string $snippet,
        bool $strict = true
    ): void {
        if (! $strict) {
            return; // Only validate in strict mode
        }

        if ($actual !== $expected) {
            $message = match ($arrayType) {
                'inline' => "Inline array length mismatch: expected $expected, got $actual",
                'list' => "List array length mismatch: expected $expected, got $actual",
                'tabular' => "Tabular array length mismatch: expected $expected rows, got $actual",
                default => "Array length mismatch: expected $expected, got $actual",
            };

            throw new CountMismatchException(
                $message,
                $expected,
                $actual,
                $lineNumber,
                $snippet
            );
        }
    }

    /**
     * Validate tabular row width matches field count (§14.4, REQ-14.4).
     *
     * Tabular row width: any row's value count ≠ field count → MUST error.
     *
     * @param  int  $expectedFieldCount  Expected field count from header
     * @param  int  $actualValueCount  Actual value count in row
     * @param  int  $lineNumber  Line number for error reporting
     * @param  string  $rowContent  Row content for error context
     *
     * @throws CountMismatchException If counts don't match
     */
    public static function validateTabularRowWidth(
        int $expectedFieldCount,
        int $actualValueCount,
        int $lineNumber,
        string $rowContent
    ): void {
        if ($actualValueCount !== $expectedFieldCount) {
            throw new CountMismatchException(
                "Tabular row width mismatch: expected $expectedFieldCount values, got $actualValueCount",
                $expectedFieldCount,
                $actualValueCount,
                $lineNumber,
                substr($rowContent, 0, 50)
            );
        }
    }

    /**
     * Validate no blank lines inside arrays/tabular rows (§14.9, REQ-14.9, §12.13, REQ-12.13, §12.17, REQ-12.17).
     *
     * Blank lines between first and last row/item in an array MUST error in strict mode.
     * - §12.13: Inside arrays/tabular rows, blank lines MUST error in strict mode
     * - §12.17: If blank line occurs between first and last row/item line in array/tabular block, MUST error
     *
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines  All lines
     * @param  int  $startIndex  Array start index
     * @param  int  $endIndex  Array end index (exclusive)
     * @param  DecodeOptions  $options  Decode options
     *
     * @throws StrictModeException If blank line found within array bounds
     */
    public static function validateNoBlankLinesInArray(
        array $lines,
        int $startIndex,
        int $endIndex,
        DecodeOptions $options
    ): void {
        if (! $options->strict) {
            return; // Only validate in strict mode
        }

        // Check for blank lines between start and end
        for ($i = $startIndex + 1; $i < $endIndex; $i++) {
            if (isset($lines[$i]['blank']) && $lines[$i]['blank'] === true) {
                throw new StrictModeException(
                    'Blank lines not allowed inside arrays/tabular rows (strict mode)',
                    $lines[$i]['line']
                );
            }
        }
    }

    /**
     * Validate missing colon (§14.5, REQ-14.5).
     *
     * Keys MUST be followed by colon.
     * Missing colon in key context → MUST error.
     *
     * @param  string  $content  Line content
     * @param  int  $lineNumber  Line number for error reporting
     *
     * @throws SyntaxException If colon is missing
     */
    public static function validateColonPresent(string $content, int $lineNumber): void
    {
        if (! str_contains($content, ':')) {
            throw new SyntaxException(
                'Missing colon after key',
                $lineNumber,
                $content
            );
        }
    }
}
