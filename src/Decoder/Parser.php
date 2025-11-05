<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Decoder;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\Exceptions\DecodeException;
use HelgeSverre\Toon\Exceptions\SyntaxException;

final class Parser
{
    public function __construct(
        private readonly DecodeOptions $options
    ) {}

    /**
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     */
    public function parse(array $lines): mixed
    {
        $nonBlankLines = array_filter($lines, fn ($line) => ! ($line['blank'] ?? false));

        if (empty($nonBlankLines)) {
            StrictValidator::validateNotEmpty($nonBlankLines, $this->options);

            return null;
        }

        $rootForm = $this->determineRootForm($lines);

        return match ($rootForm) {
            'primitive' => $this->parsePrimitive($lines[0]),
            'object' => $this->parseObject($lines, 0, 0),
            'array' => $this->parseArray($lines, 0, 0),
            default => throw new DecodeException("Invalid root form: $rootForm", 0),
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

        // Check for array header first (including those with colons like [3]:)
        if (str_starts_with($content, '[') || str_starts_with($content, '{')) {
            return 'array';
        }

        // Check if it's a keyed structure with colon
        if ($this->containsUnquotedColon($content)) {
            // Could be: items[2]: a,b (object with keyed array)
            // or: name: value (object)
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
        $inQuotes = false;
        $len = strlen($line);

        for ($i = 0; $i < $len; $i++) {
            $char = $line[$i];
            if ($char === '"' && ($i === 0 || $line[$i - 1] !== '\\')) {
                $inQuotes = ! $inQuotes;
            } elseif ($char === ':' && ! $inQuotes) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{content: string, depth: int, line: int, indent: int, blank?: bool}  $line
     */
    private function parsePrimitive(array $line): mixed
    {
        $content = trim($line['content']);

        return ValueParser::parseValue($content, $line['line']);
    }

    /**
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     * @return array<string, mixed>
     */
    private function parseObject(array $lines, int $startIndex, int $expectedDepth): array
    {
        $result = [];
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

            $parsed = $this->parseKeyValueLine($line);
            $key = $parsed['key'];
            $value = $parsed['value'];
            $header = $parsed['header'] ?? null;

            if ($i + 1 < count($lines) && $lines[$i + 1]['depth'] > $expectedDepth) {
                $nextLine = $lines[$i + 1];

                if ($header !== null && $value === null) {
                    // Keyed array with child lines - dispatch by format using header from current line
                    $value = match ($header['format']) {
                        'tabular' => $this->parseTabularArrayFromHeader($header, $lines, $i, $expectedDepth),
                        'list' => $this->parseListArrayFromHeader($header, $lines, $i, $expectedDepth),
                        default => throw new DecodeException("Invalid array format: {$header['format']}", $line['line']),
                    };
                    while ($i + 1 < count($lines) && $lines[$i + 1]['depth'] > $expectedDepth) {
                        $i++;
                    }
                } elseif (str_contains($nextLine['content'], ':') && ! DelimiterParser::isArrayHeader($nextLine['content'])) {
                    $value = $this->parseObject($lines, $i + 1, $expectedDepth + 1);
                    while ($i + 1 < count($lines) && $lines[$i + 1]['depth'] > $expectedDepth) {
                        $i++;
                    }
                } else {
                    $value = $this->parseArray($lines, $i + 1, $expectedDepth + 1);
                    while ($i + 1 < count($lines) && $lines[$i + 1]['depth'] > $expectedDepth) {
                        $i++;
                    }
                }
            }

            $result[$key] = $value;
            $i++;
        }

        return $result;
    }

    /**
     * @param  array{content: string, depth: int, line: int, indent: int, blank?: bool}  $line
     * @return array{key: string, value: mixed, header?: array{key: ?string, length: ?int, delimiter: string, fields: ?array<string>, inlineValues: ?string, format: string}}
     */
    private function parseKeyValueLine(array $line): array
    {
        $content = trim($line['content']);

        StrictValidator::validateColonPresent($content, $line['line']);

        // Try parsing as array header first (handles key[N]: formats)
        $header = HeaderParser::parseHeader($content);

        if ($header !== null && $header['key'] !== null) {
            // This is a keyed array: items[2]: a,b
            $key = $header['key'];

            if ($header['format'] === 'inline') {
                // Parse inline array directly
                $value = $this->parseInlineArrayFromHeader($header, $line['line']);
            } else {
                // Tabular or list format that needs child lines
                // Pass header metadata so parseObject knows how to parse children
                $value = null;
            }

            return ['key' => $key, 'value' => $value, 'header' => $header];
        }

        // Standard key-value parsing
        $colonPos = strpos($content, ':');
        // @phpstan-ignore-next-line - strpos won't return false here because StrictValidator::validateColonPresent already validated
        $keyPart = substr($content, 0, $colonPos);
        $valuePart = trim(substr($content, $colonPos + 1));

        $key = ValueParser::parseKey($keyPart, $line['line']);

        if ($valuePart === '') {
            return ['key' => $key, 'value' => null];
        }

        // Check if value part is an array header
        // Only append ':' if valuePart doesn't already contain inline values after a header
        // Pattern ']: ' (with space) indicates inline array like [3]: 1,2,3
        $headerForParsing = str_contains($valuePart, ']: ') ? $valuePart : $valuePart.':';
        $valueHeader = HeaderParser::parseHeader($headerForParsing);
        if ($valueHeader !== null && $valueHeader['format'] === 'inline') {
            $value = $this->parseInlineArrayFromHeader($valueHeader, $line['line']);
        } elseif (DelimiterParser::isArrayHeader($valuePart)) {
            // Tabular or list format on next lines
            $value = null;
        } else {
            $value = ValueParser::parseValue($valuePart, $line['line']);
        }

        return ['key' => $key, 'value' => $value];
    }

    /**
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     * @return array<int|string, mixed>
     */
    private function parseArray(array $lines, int $startIndex, int $expectedDepth): array
    {
        $firstLine = $lines[$startIndex];
        $content = trim($firstLine['content']);

        $header = HeaderParser::parseHeader($content);

        if ($header === null) {
            throw new DecodeException('Invalid array format', $firstLine['line']);
        }

        return match ($header['format']) {
            'inline' => $this->parseInlineArrayFromHeader($header, $firstLine['line']),
            'list' => $this->parseListArrayFromHeader($header, $lines, $startIndex, $expectedDepth),
            'tabular' => $this->parseTabularArrayFromHeader($header, $lines, $startIndex, $expectedDepth),
            default => throw new DecodeException("Invalid array format: {$header['format']}", $firstLine['line']),
        };
    }

    /**
     * @param  array{key: ?string, length: ?int, delimiter: string, fields: ?array<string>, inlineValues: ?string, format: string}  $header
     * @return array<int, mixed>
     */
    private function parseInlineArrayFromHeader(array $header, int $lineNumber): array
    {
        // Handle empty inline arrays: [0]: or [0|]:
        if ($header['inlineValues'] === null || trim($header['inlineValues']) === '') {
            if ($header['length'] !== null && $header['length'] === 0) {
                return [];
            }

            // Non-empty length declared but no values provided
            if ($header['length'] !== null && $header['length'] > 0) {
                StrictValidator::validateArrayCount($header['length'], 0, 'inline', $lineNumber, '', $this->options->strict);
            }

            return [];
        }

        $valueStrings = DelimiterParser::split($header['inlineValues'], $header['delimiter'], $lineNumber);

        $values = [];
        foreach ($valueStrings as $valueStr) {
            $values[] = ValueParser::parseValue($valueStr, $lineNumber);
        }

        if ($header['length'] !== null) {
            $actualLength = count($values);
            StrictValidator::validateArrayCount($header['length'], $actualLength, 'inline', $lineNumber, $header['inlineValues'], $this->options->strict);
        }

        return $values;
    }

    /**
     * @param  array{key: ?string, length: ?int, delimiter: string, fields: ?array<string>, inlineValues: ?string, format: string}  $header
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     * @return array<int, mixed>
     */
    private function parseListArrayFromHeader(array $header, array $lines, int $startIndex, int $expectedDepth): array
    {
        $firstLine = $lines[$startIndex];

        if ($header['length'] === null) {
            throw new SyntaxException('List array must have length', $firstLine['line'], $firstLine['content']);
        }

        $expectedLength = $header['length'];
        $items = [];
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

            if ($i + 1 < count($lines) && $lines[$i + 1]['depth'] > $line['depth']) {
                $nestedLines = [];

                if ($valueStr !== '') {
                    $nestedLines[] = [
                        'content' => $valueStr,
                        'depth' => $line['depth'] + 1,
                        'line' => $line['line'],
                        'indent' => $line['indent'] + $this->options->indent,
                    ];
                }

                $j = $i + 1;
                while ($j < count($lines) && $lines[$j]['depth'] > $line['depth']) {
                    $nestedLines[] = $lines[$j];
                    $j++;
                }

                if (DelimiterParser::isArrayHeader($nestedLines[0]['content'])) {
                    $value = $this->parseArray($nestedLines, 0, $line['depth'] + 1);
                } else {
                    $value = $this->parseObject($nestedLines, 0, $line['depth'] + 1);
                }

                $i = $j - 1;
            } elseif ($valueStr !== '') {
                $value = ValueParser::parseValue($valueStr, $line['line']);
            } else {
                $value = null;
            }

            $items[] = $value;
            $i++;
        }

        StrictValidator::validateNoBlankLinesInArray($lines, $startIndex, $lastItemIndex + 1, $this->options);

        $actualLength = count($items);
        StrictValidator::validateArrayCount($expectedLength, $actualLength, 'list', $firstLine['line'], $firstLine['content'], $this->options->strict);

        return $items;
    }

    /**
     * @param  array{key: ?string, length: ?int, delimiter: string, fields: ?array<string>, inlineValues: ?string, format: string}  $header
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function parseTabularArrayFromHeader(array $header, array $lines, int $startIndex, int $expectedDepth): array
    {
        $firstLine = $lines[$startIndex];

        if ($header['fields'] === null) {
            throw new SyntaxException('Tabular array must have fields', $firstLine['line'], $firstLine['content']);
        }

        $fields = $header['fields'];
        $expectedLength = $header['length'];
        $delimiter = $header['delimiter'];

        $fieldCount = count($fields);
        $rows = [];
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
            $valueStrings = DelimiterParser::split($rowContent, $delimiter, $line['line']);

            $valueCount = count($valueStrings);
            StrictValidator::validateTabularRowWidth($fieldCount, $valueCount, $line['line'], $rowContent);

            $rowObject = [];
            foreach ($fields as $index => $field) {
                $rowObject[$field] = ValueParser::parseValue($valueStrings[$index], $line['line']);
            }

            $rows[] = $rowObject;
            $i++;
        }

        StrictValidator::validateNoBlankLinesInArray($lines, $startIndex, $lastRowIndex + 1, $this->options);

        if ($expectedLength !== null) {
            $actualLength = count($rows);
            StrictValidator::validateArrayCount($expectedLength, $actualLength, 'tabular', $firstLine['line'], $firstLine['content'], $this->options->strict);
        }

        return $rows;
    }
}
