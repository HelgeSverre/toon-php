<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Decoder;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\Exceptions\DecodeException;
use HelgeSverre\Toon\Exceptions\StrictModeException;
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
            // §5: an empty document decodes to an empty object ({} = [] in PHP),
            // in both strict and lenient modes (§14 does not list empty input).
            return [];
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
        return $this->findUnquotedColon($line) !== null;
    }

    private function findUnquotedColon(string $line): ?int
    {
        $inQuotes = false;
        $len = strlen($line);

        for ($i = 0; $i < $len; $i++) {
            $char = $line[$i];

            if ($inQuotes) {
                // Inside a quoted string, a backslash escapes the next character
                // (§7.1), so an escaped quote does not end the string.
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

            // Duplicate sibling keys at the same depth (§8, §14.4): strict mode
            // errors; non-strict applies last-write-wins silently in document order.
            if (array_key_exists($key, $result) && $this->options->strict) {
                throw new StrictModeException("Duplicate object key: {$key}", $line['line']);
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
        $colonPos = $this->findUnquotedColon($content);
        if ($colonPos === null) {
            throw new SyntaxException('Missing colon after key', $line['line'], $content);
        }

        $keyPart = substr($content, 0, $colonPos);
        $valuePart = trim(substr($content, $colonPos + 1));

        // A valid array header would have been handled above. In strict mode, an
        // unquoted key still carrying bracket characters signals a malformed array
        // header — e.g. [03], [-1], [bar], [2]extra: — which MUST error (§6, §14.2).
        // Non-strict mode falls through, treating the whole thing as a literal key.
        $trimmedKeyPart = trim($keyPart);
        if ($this->options->strict
            && ! str_starts_with($trimmedKeyPart, '"')
            && (str_contains($trimmedKeyPart, '[') || str_contains($trimmedKeyPart, ']'))) {
            throw new SyntaxException(
                'Malformed array header (invalid bracket length or content before colon)',
                $line['line'],
                $content
            );
        }

        $key = ValueParser::parseKey($keyPart, $line['line']);

        if ($valuePart === '') {
            // §8: a bare "key:" (no value, no children) is an empty/nested object,
            // NOT an empty array or null ({} = [] in PHP). If deeper lines follow,
            // parseObject overrides this with the nested structure.
            return ['key' => $key, 'value' => []];
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
            // §9.1/§11.2: empty tokens (including whitespace-only) decode to "".
            $values[] = trim($valueStr) === '' ? '' : ValueParser::parseValue($valueStr, $lineNumber);
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
                    // §9.4: an inner array's items sit at depth +1 relative to the
                    // hyphen line, so parseArray (whose items are at expectedDepth+1)
                    // must receive the hyphen depth, not depth+1.
                    $value = $this->parseArray($nestedLines, 0, $line['depth']);
                } else {
                    // Object fields sit at the same depth as the synthetic first
                    // field (depth+1), which is parseObject's expected depth.
                    $value = $this->parseObject($nestedLines, 0, $line['depth'] + 1);
                }

                $i = $j - 1;
            } else {
                // No deeper child lines: the whole list item sits on the hyphen line.
                // Dispatch by shape (§9.2/§9.4/§10). Order matters — the array-header
                // check must precede the colon check because inner headers (- [M]: …)
                // also contain a colon.
                $syntheticLines = [[
                    'content' => $valueStr,
                    'depth' => $line['depth'] + 1,
                    'line' => $line['line'],
                    'indent' => $line['indent'] + $this->options->indent,
                ]];

                if ($valueStr === '') {
                    // §10: bare "-" is an empty-object list item ([] in PHP).
                    $value = [];
                } elseif (DelimiterParser::isArrayHeader($valueStr)) {
                    // §9.2/§9.4: inner array header fully on the hyphen line (- [M]: …).
                    $value = $this->parseArray($syntheticLines, 0, $line['depth'] + 1);
                } elseif ($this->findUnquotedColon($valueStr) !== null) {
                    // §9.4/§10: object with its first field on the hyphen line. A genuine
                    // primitive can never carry an unquoted colon or leading bracket (§7.2).
                    $value = $this->parseObject($syntheticLines, 0, $line['depth'] + 1);
                } else {
                    // §9.4: primitive list item (no colon, no array header).
                    $value = ValueParser::parseValue($valueStr, $line['line']);
                }
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
                // §11.2: empty tabular cells decode to the empty string.
                $cell = $valueStrings[$index];
                $rowObject[$field] = trim($cell) === '' ? '' : ValueParser::parseValue($cell, $line['line']);
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
