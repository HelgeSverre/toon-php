<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Decoder;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\Exceptions\DecodeException;
use HelgeSverre\Toon\Exceptions\SyntaxException;

final class Validator
{
    public function __construct(
        private readonly DecodeOptions $options
    ) {}

    /**
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     */
    public function validate(array $lines): void
    {
        $nonBlankLines = array_filter($lines, fn ($line) => ! ($line['blank'] ?? false));

        if (empty($nonBlankLines)) {
            // §5: an empty document is valid (decodes to an empty object) in both modes.
            return;
        }

        $rootForm = $this->determineRootForm($lines);

        match ($rootForm) {
            'primitive' => $this->validatePrimitive($lines[0]),
            'object' => $this->validateObject($lines, 0, 0),
            'array' => $this->validateArray($lines, 0, 0),
            default => throw new DecodeException("Invalid root form: {$rootForm}", 0),
        };
    }

    /**
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     */
    private function determineRootForm(array $lines): string
    {
        if (empty($lines)) {
            return 'object';
        }

        $firstLine = $lines[0];
        $content = trim($firstLine['content']);

        if (str_starts_with($content, '[') || str_starts_with($content, '{')) {
            return 'array';
        }

        if ($this->containsUnquotedColon($content)) {
            return 'object';
        }

        if (count($lines) === 1 && str_starts_with($content, '"')) {
            return 'primitive';
        }

        if (count($lines) === 1) {
            return 'primitive';
        }

        return 'object';
    }

    private function containsUnquotedColon(string $line): bool
    {
        return $this->findUnquotedColon($line) !== null;
    }

    private function findUnquotedColon(string $line): ?int
    {
        $inQuotes = false;
        $len = strlen($line);

        for ($i = 0; $i < $len; $i++) {
            $char = $line[$i];

            if ($inQuotes) {
                // Inside quotes, a backslash escapes the next character (§7.1).
                if ($char === '\\' && $i + 1 < $len) {
                    $i++;

                    continue;
                }
                if ($char === '"') {
                    $inQuotes = false;
                }

                continue;
            }

            if ($char === '"') {
                $inQuotes = true;
            } elseif ($char === ':') {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param  array{content: string, depth: int, line: int, indent: int, blank?: bool}  $line
     */
    private function validatePrimitive(array $line): void
    {
        ValueParser::validateValue(trim($line['content']), $line['line']);
    }

    /**
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     */
    private function validateObject(array $lines, int $startIndex, int $expectedDepth): int
    {
        $i = $startIndex;

        while ($i < count($lines)) {
            $line = $lines[$i];

            if ($line['blank'] ?? false) {
                $i++;

                continue;
            }

            if ($line['depth'] > $expectedDepth) {
                $i++;

                continue;
            }

            if ($line['depth'] < $expectedDepth) {
                break;
            }

            $parsed = $this->validateKeyValueLine($line);
            $header = $parsed['header'];
            $expectsNestedValue = $parsed['expectsNestedValue'];

            if ($i + 1 < count($lines) && $lines[$i + 1]['depth'] > $expectedDepth) {
                $nextLine = $lines[$i + 1];

                if ($header !== null && $expectsNestedValue) {
                    $i = $this->validateArrayFromHeader($header, $lines, $i, $expectedDepth);

                    continue;
                }

                if ($expectsNestedValue) {
                    if (str_contains($nextLine['content'], ':') && ! DelimiterParser::isArrayHeader($nextLine['content'])) {
                        $i = $this->validateObject($lines, $i + 1, $expectedDepth + 1);
                    } else {
                        $i = $this->validateArray($lines, $i + 1, $expectedDepth + 1);
                    }

                    continue;
                }
            }

            $i++;
        }

        return $i;
    }

    /**
     * @param  array{content: string, depth: int, line: int, indent: int, blank?: bool}  $line
     * @return array{header: array{key: ?string, length: ?int, delimiter: string, fields: ?array<string>, inlineValues: ?string, format: string}|null, expectsNestedValue: bool}
     */
    private function validateKeyValueLine(array $line): array
    {
        $content = trim($line['content']);

        StrictValidator::validateColonPresent($content, $line['line']);

        $header = HeaderParser::parseHeader($content);

        if ($header !== null && $header['key'] !== null) {
            ValueParser::validateKey($header['key'], $line['line']);

            if ($header['format'] === 'inline') {
                $this->validateInlineArrayFromHeader($header, $line['line']);

                return ['header' => $header, 'expectsNestedValue' => false];
            }

            return ['header' => $header, 'expectsNestedValue' => true];
        }

        $colonPos = $this->findUnquotedColon($content);
        if ($colonPos === null) {
            throw new SyntaxException('Missing colon after key', $line['line'], $content);
        }
        $keyPart = substr($content, 0, $colonPos);
        $valuePart = trim(substr($content, $colonPos + 1));

        ValueParser::validateKey($keyPart, $line['line']);

        if ($valuePart === '') {
            return ['header' => null, 'expectsNestedValue' => true];
        }

        $headerForParsing = str_contains($valuePart, ']: ') ? $valuePart : $valuePart.':';
        $valueHeader = HeaderParser::parseHeader($headerForParsing);

        if ($valueHeader !== null && $valueHeader['format'] === 'inline') {
            $this->validateInlineArrayFromHeader($valueHeader, $line['line']);

            return ['header' => null, 'expectsNestedValue' => false];
        }

        if (DelimiterParser::isArrayHeader($valuePart)) {
            return ['header' => null, 'expectsNestedValue' => true];
        }

        ValueParser::validateValue($valuePart, $line['line']);

        return ['header' => null, 'expectsNestedValue' => false];
    }

    /**
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     */
    private function validateArray(array $lines, int $startIndex, int $expectedDepth): int
    {
        $firstLine = $lines[$startIndex];
        $content = trim($firstLine['content']);

        $header = HeaderParser::parseHeader($content);

        if ($header === null) {
            throw new DecodeException('Invalid array format', $firstLine['line']);
        }

        return $this->validateArrayFromHeader($header, $lines, $startIndex, $expectedDepth);
    }

    /**
     * @param  array{key: ?string, length: ?int, delimiter: string, fields: ?array<string>, inlineValues: ?string, format: string}  $header
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     */
    private function validateArrayFromHeader(array $header, array $lines, int $startIndex, int $expectedDepth): int
    {
        return match ($header['format']) {
            'inline' => $this->validateInlineArrayFromLines($header, $lines, $startIndex),
            'list' => $this->validateListArrayFromHeader($header, $lines, $startIndex, $expectedDepth),
            'tabular' => $this->validateTabularArrayFromHeader($header, $lines, $startIndex, $expectedDepth),
            default => throw new DecodeException("Invalid array format: {$header['format']}", $lines[$startIndex]['line']),
        };
    }

    /**
     * @param  array{key: ?string, length: ?int, delimiter: string, fields: ?array<string>, inlineValues: ?string, format: string}  $header
     */
    private function validateInlineArrayFromHeader(array $header, int $lineNumber): void
    {
        if ($header['inlineValues'] === null || trim($header['inlineValues']) === '') {
            if ($header['length'] !== null && $header['length'] > 0) {
                StrictValidator::validateArrayCount($header['length'], 0, 'inline', $lineNumber, '', $this->options->strict);
            }

            return;
        }

        $valueStrings = DelimiterParser::split($header['inlineValues'], $header['delimiter'], $lineNumber);

        foreach ($valueStrings as $valueStr) {
            // §9.1: empty tokens are valid and decode to "".
            if (trim($valueStr) !== '') {
                ValueParser::validateValue($valueStr, $lineNumber);
            }
        }

        if ($header['length'] !== null) {
            StrictValidator::validateArrayCount(
                $header['length'],
                count($valueStrings),
                'inline',
                $lineNumber,
                $header['inlineValues'],
                $this->options->strict
            );
        }
    }

    /**
     * @param  array{key: ?string, length: ?int, delimiter: string, fields: ?array<string>, inlineValues: ?string, format: string}  $header
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     */
    private function validateInlineArrayFromLines(array $header, array $lines, int $startIndex): int
    {
        $this->validateInlineArrayFromHeader($header, $lines[$startIndex]['line']);

        return $startIndex + 1;
    }

    /**
     * @param  array{key: ?string, length: ?int, delimiter: string, fields: ?array<string>, inlineValues: ?string, format: string}  $header
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     */
    private function validateListArrayFromHeader(array $header, array $lines, int $startIndex, int $expectedDepth): int
    {
        $firstLine = $lines[$startIndex];

        if ($header['length'] === null) {
            throw new SyntaxException('List array must have length', $firstLine['line'], $firstLine['content']);
        }

        $actualLength = 0;
        $i = $startIndex + 1;
        $lastItemIndex = $startIndex;

        while ($i < count($lines)) {
            $line = $lines[$i];

            if ($line['blank'] ?? false) {
                $i++;

                continue;
            }

            if ($line['depth'] <= $expectedDepth) {
                break;
            }

            $lastItemIndex = $i;

            if ($line['depth'] !== $expectedDepth + 1) {
                throw new DecodeException('Invalid list item depth', $line['line']);
            }

            $lineContent = trim($line['content']);

            if (! str_starts_with($lineContent, '-')) {
                throw new SyntaxException('List array items must start with hyphen marker', $line['line'], $lineContent);
            }

            $valueStr = ltrim(substr($lineContent, 1));
            $i = $this->validateListItem($valueStr, $lines, $i, $line);
            $lastItemIndex = max($lastItemIndex, $i - 1);
            $actualLength++;
        }

        StrictValidator::validateNoBlankLinesInArray($lines, $startIndex, $lastItemIndex + 1, $this->options);
        StrictValidator::validateArrayCount(
            $header['length'],
            $actualLength,
            'list',
            $firstLine['line'],
            $firstLine['content'],
            $this->options->strict
        );

        return $i;
    }

    /**
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     * @param  array{content: string, depth: int, line: int, indent: int, blank?: bool}  $line
     */
    private function validateListItem(string $valueStr, array $lines, int $itemIndex, array $line): int
    {
        $hasChildren = $itemIndex + 1 < count($lines) && $lines[$itemIndex + 1]['depth'] > $line['depth'];

        if ($hasChildren) {
            if ($valueStr === '') {
                $nextLine = $lines[$itemIndex + 1];

                if (DelimiterParser::isArrayHeader($nextLine['content'])) {
                    return $this->validateArray($lines, $itemIndex + 1, $line['depth'] + 1);
                }

                return $this->validateObject($lines, $itemIndex + 1, $line['depth'] + 1);
            }

            if (DelimiterParser::isArrayHeader($valueStr)) {
                $header = HeaderParser::parseHeader($valueStr);

                if ($header === null) {
                    throw new DecodeException('Invalid array format', $line['line']);
                }

                return $this->validateVirtualArray($header, $lines, $itemIndex, $line);
            }

            return $this->validateVirtualObject($valueStr, $lines, $itemIndex, $line);
        }

        if ($valueStr === '') {
            return $itemIndex + 1;
        }

        if (DelimiterParser::isArrayHeader($valueStr)) {
            $header = HeaderParser::parseHeader($valueStr);

            if ($header === null) {
                throw new DecodeException('Invalid array format', $line['line']);
            }

            if ($header['format'] !== 'inline') {
                throw new DecodeException('Invalid list item array format', $line['line']);
            }

            $this->validateInlineArrayFromHeader($header, $line['line']);

            return $itemIndex + 1;
        }

        // A list item is an object only when it carries an UNQUOTED colon (§9.4);
        // a quoted colon (e.g. - "x: y") is a primitive string.
        if ($this->findUnquotedColon($valueStr) !== null) {
            $this->validateVirtualObject($valueStr, $lines, $itemIndex, $line);

            return $itemIndex + 1;
        }

        ValueParser::validateValue($valueStr, $line['line']);

        return $itemIndex + 1;
    }

    /**
     * @param  array{key: ?string, length: ?int, delimiter: string, fields: ?array<string>, inlineValues: ?string, format: string}  $header
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     * @param  array{content: string, depth: int, line: int, indent: int, blank?: bool}  $line
     */
    private function validateVirtualArray(array $header, array $lines, int $itemIndex, array $line): int
    {
        return match ($header['format']) {
            'inline' => $this->validateVirtualInlineArray($header, $itemIndex, $line),
            'list' => $this->validateVirtualListArray($header, $lines, $itemIndex, $line),
            'tabular' => $this->validateVirtualTabularArray($header, $lines, $itemIndex, $line),
            default => throw new DecodeException("Invalid array format: {$header['format']}", $line['line']),
        };
    }

    /**
     * @param  array{key: ?string, length: ?int, delimiter: string, fields: ?array<string>, inlineValues: ?string, format: string}  $header
     * @param  array{content: string, depth: int, line: int, indent: int, blank?: bool}  $line
     */
    private function validateVirtualInlineArray(array $header, int $itemIndex, array $line): int
    {
        $this->validateInlineArrayFromHeader($header, $line['line']);

        return $itemIndex + 1;
    }

    /**
     * @param  array{key: ?string, length: ?int, delimiter: string, fields: ?array<string>, inlineValues: ?string, format: string}  $header
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     * @param  array{content: string, depth: int, line: int, indent: int, blank?: bool}  $line
     */
    private function validateVirtualListArray(array $header, array $lines, int $itemIndex, array $line): int
    {
        if ($header['length'] === null) {
            throw new SyntaxException('List array must have length', $line['line'], trim($line['content']));
        }

        $actualLength = 0;
        $i = $itemIndex + 1;
        $lastItemIndex = $itemIndex;
        $expectedDepth = $line['depth'] + 1;

        while ($i < count($lines)) {
            $current = $lines[$i];

            if ($current['blank'] ?? false) {
                $i++;

                continue;
            }

            if ($current['depth'] <= $line['depth']) {
                break;
            }

            $lastItemIndex = $i;

            if ($current['depth'] !== $expectedDepth) {
                throw new DecodeException('Invalid list item depth', $current['line']);
            }

            $lineContent = trim($current['content']);
            if (! str_starts_with($lineContent, '-')) {
                throw new SyntaxException('List array items must start with hyphen marker', $current['line'], $lineContent);
            }

            $valueStr = ltrim(substr($lineContent, 1));
            $i = $this->validateListItem($valueStr, $lines, $i, $current);
            $lastItemIndex = max($lastItemIndex, $i - 1);
            $actualLength++;
        }

        StrictValidator::validateNoBlankLinesInArray($lines, $itemIndex, $lastItemIndex + 1, $this->options);
        StrictValidator::validateArrayCount(
            $header['length'],
            $actualLength,
            'list',
            $line['line'],
            trim($line['content']),
            $this->options->strict
        );

        return $i;
    }

    /**
     * @param  array{key: ?string, length: ?int, delimiter: string, fields: ?array<string>, inlineValues: ?string, format: string}  $header
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     */
    private function validateTabularArrayFromHeader(array $header, array $lines, int $startIndex, int $expectedDepth): int
    {
        $firstLine = $lines[$startIndex];

        if ($header['fields'] === null) {
            throw new SyntaxException('Tabular array must have fields', $firstLine['line'], $firstLine['content']);
        }

        foreach ($header['fields'] as $field) {
            ValueParser::validateKey($field, $firstLine['line']);
        }

        $actualLength = 0;
        $i = $startIndex + 1;
        $lastRowIndex = $startIndex;

        while ($i < count($lines)) {
            $line = $lines[$i];

            if ($line['blank'] ?? false) {
                $i++;

                continue;
            }

            if ($line['depth'] <= $expectedDepth) {
                break;
            }

            $lastRowIndex = $i;

            if ($line['depth'] !== $expectedDepth + 1) {
                throw new DecodeException('Invalid tabular row depth', $line['line']);
            }

            $rowContent = trim($line['content']);
            $valueStrings = DelimiterParser::split($rowContent, $header['delimiter'], $line['line']);

            StrictValidator::validateTabularRowWidth(count($header['fields']), count($valueStrings), $line['line'], $rowContent);

            foreach ($valueStrings as $valueStr) {
                if (trim($valueStr) !== '') {
                    ValueParser::validateValue($valueStr, $line['line']);
                }
            }

            $actualLength++;
            $i++;
        }

        StrictValidator::validateNoBlankLinesInArray($lines, $startIndex, $lastRowIndex + 1, $this->options);

        if ($header['length'] !== null) {
            StrictValidator::validateArrayCount(
                $header['length'],
                $actualLength,
                'tabular',
                $firstLine['line'],
                $firstLine['content'],
                $this->options->strict
            );
        }

        return $i;
    }

    /**
     * @param  array{key: ?string, length: ?int, delimiter: string, fields: ?array<string>, inlineValues: ?string, format: string}  $header
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     * @param  array{content: string, depth: int, line: int, indent: int, blank?: bool}  $line
     */
    private function validateVirtualTabularArray(array $header, array $lines, int $itemIndex, array $line): int
    {
        if ($header['fields'] === null) {
            throw new SyntaxException('Tabular array must have fields', $line['line'], trim($line['content']));
        }

        foreach ($header['fields'] as $field) {
            ValueParser::validateKey($field, $line['line']);
        }

        $actualLength = 0;
        $i = $itemIndex + 1;
        $lastRowIndex = $itemIndex;
        $expectedDepth = $line['depth'] + 2;

        while ($i < count($lines)) {
            $current = $lines[$i];

            if ($current['blank'] ?? false) {
                $i++;

                continue;
            }

            if ($current['depth'] <= $line['depth']) {
                break;
            }

            $lastRowIndex = $i;

            if ($current['depth'] !== $expectedDepth) {
                throw new DecodeException('Invalid tabular row depth', $current['line']);
            }

            $rowContent = trim($current['content']);
            $valueStrings = DelimiterParser::split($rowContent, $header['delimiter'], $current['line']);

            StrictValidator::validateTabularRowWidth(count($header['fields']), count($valueStrings), $current['line'], $rowContent);

            foreach ($valueStrings as $valueStr) {
                if (trim($valueStr) !== '') {
                    ValueParser::validateValue($valueStr, $current['line']);
                }
            }

            $actualLength++;
            $i++;
        }

        StrictValidator::validateNoBlankLinesInArray($lines, $itemIndex, $lastRowIndex + 1, $this->options);

        if ($header['length'] !== null) {
            StrictValidator::validateArrayCount(
                $header['length'],
                $actualLength,
                'tabular',
                $line['line'],
                trim($line['content']),
                $this->options->strict
            );
        }

        return $i;
    }

    /**
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     * @param  array{content: string, depth: int, line: int, indent: int, blank?: bool}  $line
     */
    private function validateVirtualObject(string $valueStr, array $lines, int $itemIndex, array $line): int
    {
        $virtualLine = [
            'content' => $valueStr,
            'depth' => $line['depth'] + 1,
            'line' => $line['line'],
            'indent' => $line['indent'] + $this->options->indent,
            'blank' => false,
        ];

        $parsed = $this->validateKeyValueLine($virtualLine);
        $header = $parsed['header'];
        $expectsNestedValue = $parsed['expectsNestedValue'];

        $i = $itemIndex + 1;

        if ($expectsNestedValue && $i < count($lines) && $lines[$i]['depth'] > $line['depth']) {
            if ($header !== null) {
                return $this->validateVirtualArray($header, $lines, $itemIndex, $line);
            }

            $nextLine = $lines[$i];
            if (DelimiterParser::isArrayHeader($nextLine['content'])) {
                return $this->validateArray($lines, $i, $line['depth'] + 1);
            }

            return $this->validateObject($lines, $i, $line['depth'] + 1);
        }

        while ($i < count($lines)) {
            $current = $lines[$i];

            if ($current['blank'] ?? false) {
                $i++;

                continue;
            }

            if ($current['depth'] <= $line['depth']) {
                break;
            }

            if ($current['depth'] !== $line['depth'] + 1) {
                throw new DecodeException('Invalid object field depth', $current['line']);
            }

            $sibling = $this->validateKeyValueLine($current);
            $siblingHeader = $sibling['header'];
            $siblingNeedsNestedValue = $sibling['expectsNestedValue'];

            if ($siblingNeedsNestedValue && $i + 1 < count($lines) && $lines[$i + 1]['depth'] > $current['depth']) {
                if ($siblingHeader !== null) {
                    $i = $this->validateArrayFromHeader($siblingHeader, $lines, $i, $current['depth']);

                    continue;
                }

                $nextLine = $lines[$i + 1];
                if (DelimiterParser::isArrayHeader($nextLine['content'])) {
                    $i = $this->validateArray($lines, $i + 1, $current['depth'] + 1);
                } else {
                    $i = $this->validateObject($lines, $i + 1, $current['depth'] + 1);
                }

                continue;
            }

            $i++;
        }

        return $i;
    }
}
