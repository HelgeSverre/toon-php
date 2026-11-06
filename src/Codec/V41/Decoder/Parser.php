<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Codec\V41\Decoder;

use HelgeSverre\Toon\Codec\V41\Fields;
use HelgeSverre\Toon\Constants;
use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\Exceptions\DecodeException;
use HelgeSverre\Toon\Exceptions\StrictModeException;
use HelgeSverre\Toon\Exceptions\SyntaxException;

/** @internal Version-specific codec implementation. */
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
        $lines = array_values($lines);
        $firstIndex = $this->nextContentIndex($lines, 0);

        if ($firstIndex === null) {
            // §5: an empty document – and one holding only comment and blank lines –
            // decodes to an empty object ({} = [] in PHP).
            return [];
        }

        $first = $lines[$firstIndex];
        $content = trim($first['content'], ' ');

        // §5 root form discovery, applied to the comment-stripped line sequence.
        if ($first['depth'] === 0) {
            $defect = null;
            $header = HeaderParser::parseHeader($content, $defect);

            if ($header !== null && ! $header['hasKey']) {
                $value = $header['keyed']
                    ? $this->parseKeyedTabularFromHeader($header, $lines, $firstIndex, 0)
                    : $this->parseArrayFromHeader($header, $lines, $firstIndex, 0);

                $this->validateNoTrailingContent($lines, $firstIndex, 0, $header['format'] !== 'inline');

                return $value;
            }

            // §5/§9.1: the literal token "[]" is an empty root array.
            if ($content === Constants::EMPTY_ARRAY) {
                $this->validateNoTrailingContent($lines, $firstIndex, 0, false);

                return [];
            }

            if ($defect !== null && $this->options->strict) {
                throw new SyntaxException($defect, $first['line'], $content);
            }
        }

        // §5: a single non-blank line that is neither an array header nor a
        // key-value line is a root primitive.
        if ($this->nextContentIndex($lines, $firstIndex + 1) === null
            && $this->findUnquotedColon($content) === null) {
            return $this->parsePrimitive($first);
        }

        return $this->parseObject($lines, $firstIndex, 0);
    }

    /**
     * @param  array{content: string, depth: int, line: int, indent: int, blank?: bool}  $line
     */
    private function parsePrimitive(array $line): mixed
    {
        return ValueParser::parseValue(trim($line['content'], ' '), $line['line']);
    }

    /**
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     * @return array<string, mixed>
     */
    private function parseObject(array $lines, int $startIndex, int $expectedDepth): array
    {
        $result = [];
        $count = count($lines);
        $i = $startIndex;

        while ($i < $count) {
            $line = $lines[$i];

            if ($line['blank'] ?? false) {
                $i++;

                continue;
            }

            if ($line['depth'] > $expectedDepth) {
                // §8/§14.2: a line deeper than the content depth of its enclosing
                // scope whose preceding line did not open a scope belongs to no
                // scope. Strict decoders MUST error; non-strict ones MAY skip it.
                if ($this->options->strict) {
                    throw new StrictModeException(
                        'Over-indented line: no enclosing scope opens at this depth',
                        $line['line']
                    );
                }

                $i++;

                continue;
            }

            if ($line['depth'] < $expectedDepth) {
                break;
            }

            $parsed = $this->parseKeyValueLine($line);
            $key = $parsed['key'];
            $value = $parsed['value'];
            $header = $parsed['header'];

            if ($header !== null && $header['format'] !== 'inline') {
                $this->validateChildDepth($lines, $i, $expectedDepth);

                $value = match ($header['format']) {
                    'tabular' => $this->parseTabularArrayFromHeader($header, $lines, $i, $expectedDepth),
                    'keyed' => $this->parseKeyedTabularFromHeader($header, $lines, $i, $expectedDepth),
                    'list' => $this->parseListArrayFromHeader($header, $lines, $i, $expectedDepth),
                    default => throw new DecodeException("Invalid array format: {$header['format']}", $line['line']),
                };

                $i = $this->scopeEndIndex($lines, $i, $expectedDepth);
            } elseif ($parsed['opensScope']) {
                $childIndex = $this->nextContentIndex($lines, $i + 1);

                if ($childIndex !== null && $lines[$childIndex]['depth'] > $expectedDepth) {
                    // §8: "key:" with deeper lines below opens a nested object.
                    $this->validateChildDepth($lines, $i, $expectedDepth);
                    $value = $this->parseObject($lines, $childIndex, $expectedDepth + 1);
                    $i = $this->scopeEndIndex($lines, $i, $expectedDepth);
                }
            }

            // Duplicate sibling keys at the same depth (§8, §14.3): strict mode
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
     * @return array{key: string, value: mixed, header: ?array{key: ?string, hasKey: bool, length: ?int, keyed: bool, delimiter: string, fields: ?array<int, array{name: string, children: array<int, mixed>|null}>, inlineValues: ?string, format: string}, opensScope: bool}
     */
    private function parseKeyValueLine(array $line): array
    {
        $content = trim($line['content'], ' ');

        StrictValidator::validateColonPresent($content, $line['line']);

        $defect = null;
        $header = HeaderParser::parseHeader($content, $defect);

        if ($header !== null) {
            // §6/§14.2: a keyless header is valid only as the document's root header
            // or (without a field list) as a list item.
            if (! $header['hasKey']) {
                if ($this->options->strict) {
                    throw new SyntaxException(
                        'Keyless header outside its valid position',
                        $line['line'],
                        $content
                    );
                }
            } else {
                $key = $header['key'];
                assert($key !== null);

                return [
                    'key' => $key,
                    'value' => $header['format'] === 'inline'
                        ? $this->parseInlineArrayFromHeader($header, $line['line'])
                        : null,
                    'header' => $header,
                    'opensScope' => false,
                ];
            }
        } elseif ($defect !== null && $this->options->strict) {
            // §6/§14.2: a malformed header is a strict error; non-strict decoders
            // fall through to key-value parsing with the key as a literal token.
            throw new SyntaxException($defect, $line['line'], $content);
        }

        $colonPos = $this->findUnquotedColon($content);
        if ($colonPos === null) {
            throw new SyntaxException('Missing colon after key', $line['line'], $content);
        }

        $key = ValueParser::parseKey(substr($content, 0, $colonPos), $line['line']);
        $valuePart = trim(substr($content, $colonPos + 1), ' ');

        if ($valuePart === '') {
            // §8: a bare "key:" is an empty or nested object, never an empty array
            // ({} = [] in PHP). parseObject overrides this when deeper lines follow.
            return ['key' => $key, 'value' => [], 'header' => null, 'opensScope' => true];
        }

        // §9.1: "key: []" is the canonical empty-array field form.
        if ($valuePart === Constants::EMPTY_ARRAY) {
            return ['key' => $key, 'value' => [], 'header' => null, 'opensScope' => false];
        }

        // §11.2: the entire post-colon token is a single value.
        return [
            'key' => $key,
            'value' => ValueParser::parseValue($valuePart, $line['line']),
            'header' => null,
            'opensScope' => false,
        ];
    }

    /**
     * @param  array{key: ?string, hasKey: bool, length: ?int, keyed: bool, delimiter: string, fields: ?array<int, array{name: string, children: array<int, mixed>|null}>, inlineValues: ?string, format: string}  $header
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     * @return array<int, mixed>
     */
    private function parseArrayFromHeader(array $header, array $lines, int $startIndex, int $expectedDepth): array
    {
        return match ($header['format']) {
            'inline' => $this->parseInlineArrayFromHeader($header, $lines[$startIndex]['line']),
            'list' => $this->parseListArrayFromHeader($header, $lines, $startIndex, $expectedDepth),
            'tabular' => $this->parseTabularArrayFromHeader($header, $lines, $startIndex, $expectedDepth),
            default => throw new DecodeException("Invalid array format: {$header['format']}", $lines[$startIndex]['line']),
        };
    }

    /**
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     * @return array<int|string, mixed>
     */
    private function parseArray(array $lines, int $startIndex, int $expectedDepth): array
    {
        $firstLine = $lines[$startIndex];
        $content = trim($firstLine['content'], ' ');

        // §9.1/§9.2: the bare item "[]" is an empty inner array.
        if ($content === Constants::EMPTY_ARRAY) {
            return [];
        }

        $defect = null;
        $header = HeaderParser::parseHeader($content, $defect);

        if ($header === null) {
            throw new SyntaxException($defect ?? 'Invalid array format', $firstLine['line'], $content);
        }

        // §6/§14.2: a keyless fields-bearing header is valid only at the root.
        // Strict decoders MUST error; non-strict ones fall through to key-value
        // parsing, with the key treated as a literal token.
        if (! $header['hasKey'] && $header['fields'] !== null) {
            if ($this->options->strict) {
                throw new SyntaxException(
                    'Keyless fields-bearing header outside the document root',
                    $firstLine['line'],
                    $content
                );
            }

            return $this->parseObject($lines, $startIndex, $firstLine['depth']);
        }

        if ($header['keyed']) {
            return $this->parseKeyedTabularFromHeader($header, $lines, $startIndex, $expectedDepth);
        }

        return $this->parseArrayFromHeader($header, $lines, $startIndex, $expectedDepth);
    }

    /**
     * @param  array{key: ?string, hasKey: bool, length: ?int, keyed: bool, delimiter: string, fields: ?array<int, array{name: string, children: array<int, mixed>|null}>, inlineValues: ?string, format: string}  $header
     * @return array<int, mixed>
     */
    private function parseInlineArrayFromHeader(array $header, int $lineNumber): array
    {
        $length = $header['length'];

        if ($header['inlineValues'] === null || trim($header['inlineValues'], ' ') === '') {
            if ($length !== null && $length > 0) {
                StrictValidator::validateArrayCount($length, 0, 'inline', $lineNumber, '', $this->options->strict);
            }

            return [];
        }

        $values = $this->decodeCells(
            DelimiterParser::split($header['inlineValues'], $header['delimiter'], $lineNumber),
            $lineNumber
        );

        if ($length !== null) {
            StrictValidator::validateArrayCount($length, count($values), 'inline', $lineNumber, $header['inlineValues'], $this->options->strict);
        }

        return $values;
    }

    /**
     * @param  array{key: ?string, hasKey: bool, length: ?int, keyed: bool, delimiter: string, fields: ?array<int, array{name: string, children: array<int, mixed>|null}>, inlineValues: ?string, format: string}  $header
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     * @return array<int, mixed>
     */
    private function parseListArrayFromHeader(array $header, array $lines, int $startIndex, int $expectedDepth): array
    {
        $firstLine = $lines[$startIndex];

        $expectedLength = $header['length'];
        if ($expectedLength === null) {
            throw new SyntaxException('List array must have length', $firstLine['line'], $firstLine['content']);
        }

        $items = [];
        $count = count($lines);
        $i = $startIndex + 1;
        // §12: a header's span runs from its first item/row/entry line, not from
        // the header line, so a blank line before the first one is ignored.
        $firstItemIndex = null;
        $lastItemIndex = $startIndex;

        while ($i < $count) {
            $line = $lines[$i];

            if ($line['blank'] ?? false) {
                $i++;

                continue;
            }

            if ($line['depth'] <= $expectedDepth) {
                break;
            }

            if ($line['depth'] !== $expectedDepth + 1) {
                // §8/§14.2: an item deeper than the scope's content depth.
                if ($this->options->strict) {
                    throw new StrictModeException('Invalid list item depth', $line['line']);
                }

                $i++;

                continue;
            }

            $firstItemIndex ??= $i;
            $lastItemIndex = $i;
            $lineContent = trim($line['content'], ' ');

            // §5.2: a list item is the bare marker "-" or begins with "- ".
            if ($lineContent !== Constants::LIST_ITEM_MARKER && ! str_starts_with($lineContent, Constants::LIST_ITEM_PREFIX)) {
                // A terminating line at item depth belongs to no enclosing scope.
                // Bare scalar lines are invalid in either mode (§5.2).
                if ($this->options->strict || $this->findUnquotedColon($lineContent) === null) {
                    throw new SyntaxException('Line at list item depth belongs to no scope', $line['line'], $lineContent);
                }
                break;
            }

            $valueStr = ltrim(substr($lineContent, 1), ' ');
            $childIndex = $this->nextContentIndex($lines, $i + 1);
            $hasChildren = $childIndex !== null && $lines[$childIndex]['depth'] > $line['depth'];

            if ($hasChildren) {
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
                while ($j < $count && (($lines[$j]['blank'] ?? false) || $lines[$j]['depth'] > $line['depth'])) {
                    if (($lines[$j]['blank'] ?? false) && $this->nextContentDepth($lines, $j) <= $line['depth']) {
                        break;
                    }
                    $nestedLines[] = $lines[$j];
                    $lastItemIndex = $j;
                    $j++;
                }

                if (DelimiterParser::isArrayHeader($nestedLines[0]['content'])) {
                    // §9.4: an inner array's items sit at depth +1 relative to the
                    // hyphen line, so parseArray (whose items are at expectedDepth+1)
                    // must receive the hyphen depth, not depth+1.
                    $value = $this->parseArray($nestedLines, 0, $line['depth']);
                } else {
                    // §10: the first field carried on the hyphen line stands at depth
                    // d+1 alongside the object's remaining fields.
                    $value = $this->parseObject($nestedLines, 0, $line['depth'] + 1);
                }

                $i = $j - 1;
            } else {
                // The whole list item sits on the hyphen line. Order matters: the
                // array-header check must precede the colon check, because inner
                // headers (- [M]: …) also carry a colon.
                $syntheticLines = [[
                    'content' => $valueStr,
                    'depth' => $line['depth'] + 1,
                    'line' => $line['line'],
                    'indent' => $line['indent'] + $this->options->indent,
                ]];

                if ($valueStr === '') {
                    // §10: bare "-" is an empty-object list item ([] in PHP).
                    $value = [];
                } elseif ($valueStr === Constants::EMPTY_ARRAY) {
                    // §9.2: decoders accept "- []" as an empty inner array.
                    $value = [];
                } elseif (DelimiterParser::isArrayHeader($valueStr)) {
                    // §9.2/§9.4: inner array header fully on the hyphen line.
                    $value = $this->parseArray($syntheticLines, 0, $line['depth'] + 1);
                } elseif ($this->findUnquotedColon($valueStr) !== null) {
                    // §9.4/§10: object with its first field on the hyphen line.
                    $value = $this->parseObject($syntheticLines, 0, $line['depth'] + 1);
                } else {
                    // §9.4: primitive list item (no colon, no array header).
                    $value = ValueParser::parseValue($valueStr, $line['line']);
                }
            }

            $items[] = $value;
            $i++;
        }

        StrictValidator::validateNoBlankLinesInArray($lines, $firstItemIndex, $lastItemIndex + 1, $this->options);

        StrictValidator::validateArrayCount($expectedLength, count($items), 'list', $firstLine['line'], $firstLine['content'], $this->options->strict);

        return $items;
    }

    /**
     * Decode a tabular array: a fields-bearing header followed by one row per element (§9.3).
     *
     * @param  array{key: ?string, hasKey: bool, length: ?int, keyed: bool, delimiter: string, fields: ?array<int, array{name: string, children: array<int, mixed>|null}>, inlineValues: ?string, format: string}  $header
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function parseTabularArrayFromHeader(array $header, array $lines, int $startIndex, int $expectedDepth): array
    {
        $firstLine = $lines[$startIndex];
        $fields = $header['fields'];

        if ($fields === null) {
            throw new SyntaxException('Tabular array must have fields', $firstLine['line'], $firstLine['content']);
        }

        $this->validateNoDuplicateFieldNames($fields, $firstLine['line'], trim($firstLine['content'], ' '));

        $leafCount = Fields::leafCount($fields);
        $rowDepth = $expectedDepth + 1;
        $rows = [];
        $count = count($lines);
        $i = $startIndex + 1;
        // §12: the header span starts at the first row line (see §9.2/§9.4 above).
        $firstRowIndex = null;
        $lastRowIndex = $startIndex;

        while ($i < $count) {
            $line = $lines[$i];

            if ($line['blank'] ?? false) {
                $i++;

                continue;
            }

            if ($line['depth'] <= $expectedDepth) {
                break;
            }

            $rowContent = trim($line['content'], ' ');

            if ($line['depth'] !== $rowDepth) {
                if ($this->options->strict) {
                    throw new StrictModeException('Invalid tabular row depth', $line['line']);
                }

                $i++;

                continue;
            }

            // §9.3 disambiguation at row depth: a colon before the first unquoted
            // active delimiter ends the rows. Such a line is not itself a row.
            if (! $this->isRowLine($rowContent, $header['delimiter'])) {
                if ($this->options->strict) {
                    throw new StrictModeException(
                        'Line at tabular row depth is not a row and belongs to no scope',
                        $line['line']
                    );
                }

                break;
            }

            $firstRowIndex ??= $i;
            $lastRowIndex = $i;

            $cells = $this->decodeCells(
                DelimiterParser::split($rowContent, $header['delimiter'], $line['line']),
                $line['line']
            );

            if ($this->options->strict) {
                StrictValidator::validateTabularRowWidth($leafCount, count($cells), $line['line'], $rowContent);
            }

            $index = 0;
            $rows[] = Fields::materialize($fields, $cells, $index);
            $i++;
        }

        StrictValidator::validateNoBlankLinesInArray($lines, $firstRowIndex, $lastRowIndex + 1, $this->options);

        if ($header['length'] !== null) {
            StrictValidator::validateArrayCount($header['length'], count($rows), 'tabular', $firstLine['line'], $firstLine['content'], $this->options->strict);
        }

        return $rows;
    }

    /**
     * Decode a keyed tabular object: a keyed header followed by one entry row per entry (§9.5).
     *
     * @param  array{key: ?string, hasKey: bool, length: ?int, keyed: bool, delimiter: string, fields: ?array<int, array{name: string, children: array<int, mixed>|null}>, inlineValues: ?string, format: string}  $header
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     * @return array<string, mixed>
     */
    private function parseKeyedTabularFromHeader(array $header, array $lines, int $startIndex, int $expectedDepth): array
    {
        $firstLine = $lines[$startIndex];
        $fields = $header['fields'];

        if ($fields === null) {
            throw new SyntaxException('Keyed header requires a field list', $firstLine['line'], $firstLine['content']);
        }

        $this->validateNoDuplicateFieldNames($fields, $firstLine['line'], trim($firstLine['content'], ' '));

        $leafCount = Fields::leafCount($fields);
        $entryDepth = $expectedDepth + 1;
        $result = [];
        $entryCount = 0;
        $count = count($lines);
        $i = $startIndex + 1;
        // §12: the header span starts at the first entry row line.
        $firstEntryIndex = null;
        $lastEntryIndex = $startIndex;

        while ($i < $count) {
            $line = $lines[$i];

            if ($line['blank'] ?? false) {
                $i++;

                continue;
            }

            // §9.5: a keyed scope ends only when the depth decreases to the header's
            // depth or less, or at end of input.
            if ($line['depth'] <= $expectedDepth) {
                break;
            }

            $rowContent = trim($line['content'], ' ');

            if ($line['depth'] !== $entryDepth) {
                if ($this->options->strict) {
                    throw new StrictModeException('Invalid keyed entry row depth', $line['line']);
                }

                $i++;

                continue;
            }

            $colonPos = $this->findUnquotedColon($rowContent);
            if ($colonPos === null) {
                // §9.5/§14.2: a line at entry depth without an unquoted colon.
                if ($this->options->strict) {
                    throw new StrictModeException('Line at keyed entry depth has no unquoted colon', $line['line']);
                }

                $i++;

                continue;
            }

            $firstEntryIndex ??= $i;
            $lastEntryIndex = $i;
            $entryCount++;

            $entryKey = ValueParser::parseKey(substr($rowContent, 0, $colonPos), $line['line']);
            $rest = trim(substr($rowContent, $colonPos + 1), ' ');

            // §11.2: content that trims to nothing is zero cells, not one empty cell.
            $cells = $rest === ''
                ? []
                : $this->decodeCells(DelimiterParser::split($rest, $header['delimiter'], $line['line']), $line['line']);

            if ($this->options->strict) {
                StrictValidator::validateTabularRowWidth($leafCount, count($cells), $line['line'], $rowContent);
            }

            $index = 0;
            $entryValue = Fields::materialize($fields, $cells, $index);

            // §9.5/§14.3: entry keys are sibling keys of the decoded object.
            if (array_key_exists($entryKey, $result) && $this->options->strict) {
                throw new StrictModeException("Duplicate object key: {$entryKey}", $line['line']);
            }

            $result[$entryKey] = $entryValue;
            $i++;
        }

        StrictValidator::validateNoBlankLinesInArray($lines, $firstEntryIndex, $lastEntryIndex + 1, $this->options);

        if ($header['length'] !== null) {
            StrictValidator::validateArrayCount($header['length'], $entryCount, 'keyed', $firstLine['line'], $firstLine['content'], $this->options->strict);
        }

        return $result;
    }

    /**
     * §9.3 row/key-value disambiguation, evaluated on unquoted positions only.
     */
    private function isRowLine(string $content, string $delimiter): bool
    {
        $colonPos = HeaderParser::findUnquoted($content, Constants::COLON);

        if ($colonPos === false) {
            return true;
        }

        $delimiterPos = HeaderParser::findUnquoted($content, $delimiter);

        return $delimiterPos !== false && $delimiterPos < $colonPos;
    }

    /**
     * §11.2: split tokens are trimmed of U+0020 and empty tokens decode to "".
     *
     * @param  array<int, string>  $tokens
     * @return array<int, mixed>
     */
    private function decodeCells(array $tokens, int $lineNumber): array
    {
        $values = [];
        foreach ($tokens as $token) {
            $values[] = trim($token, ' ') === '' ? '' : ValueParser::parseValue($token, $lineNumber);
        }

        return $values;
    }

    /**
     * §9.3/§14.2: a field name repeated within the same field list is a header
     * defect, diagnosed from the header line alone. Names repeated at different
     * nesting levels are not duplicates.
     *
     * @param  array<int, array{name: string, children: array<int, mixed>|null}>  $fields
     */
    private function validateNoDuplicateFieldNames(array $fields, int $lineNumber, string $snippet): void
    {
        $seen = [];
        foreach ($fields as $field) {
            if (isset($seen[$field['name']])) {
                if ($this->options->strict) {
                    throw new SyntaxException(
                        "Duplicate field name in header field list: {$field['name']}",
                        $lineNumber,
                        $snippet
                    );
                }
            }

            $seen[$field['name']] = true;

            if ($field['children'] !== null) {
                /** @var array<int, array{name: string, children: array<int, mixed>|null}> $children */
                $children = $field['children'];
                $this->validateNoDuplicateFieldNames($children, $lineNumber, $snippet);
            }
        }
    }

    /**
     * §8/§14.2: the first line of a non-empty nested scope MUST be at exactly depth d+1.
     *
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     */
    private function validateChildDepth(array $lines, int $headerIndex, int $headerDepth): void
    {
        if (! $this->options->strict) {
            return;
        }

        $childIndex = $this->nextContentIndex($lines, $headerIndex + 1);
        if ($childIndex === null) {
            return;
        }

        $childDepth = $lines[$childIndex]['depth'];
        if ($childDepth > $headerDepth + 1) {
            throw new StrictModeException(
                'Indentation depth jump: nested scope starts more than one level deeper',
                $lines[$childIndex]['line']
            );
        }
    }

    /**
     * §5/§14.2: once a root array or keyed tabular root object is complete, no
     * further non-comment, non-blank line may follow.
     *
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     */
    private function validateNoTrailingContent(array $lines, int $rootIndex, int $rootDepth, bool $opensScope): void
    {
        if (! $this->options->strict) {
            return;
        }

        $endIndex = $opensScope ? $this->scopeEndIndex($lines, $rootIndex, $rootDepth) : $rootIndex;
        $trailingIndex = $this->nextContentIndex($lines, $endIndex + 1);

        if ($trailingIndex !== null) {
            throw new StrictModeException(
                'Trailing content after a completed root form',
                $lines[$trailingIndex]['line']
            );
        }
    }

    /**
     * Index of the last line belonging to the scope opened at $startIndex.
     *
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     */
    private function scopeEndIndex(array $lines, int $startIndex, int $expectedDepth): int
    {
        $count = count($lines);
        $last = $startIndex;

        for ($j = $startIndex + 1; $j < $count; $j++) {
            if ($lines[$j]['blank'] ?? false) {
                continue;
            }

            if ($lines[$j]['depth'] <= $expectedDepth) {
                break;
            }

            $last = $j;
        }

        return $last;
    }

    /**
     * Index of the next non-blank line at or after $from, or null when there is none.
     *
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     */
    private function nextContentIndex(array $lines, int $from): ?int
    {
        $count = count($lines);

        for ($i = $from; $i < $count; $i++) {
            if (! ($lines[$i]['blank'] ?? false)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Depth of the next non-blank line at or after $from; -1 when there is none.
     *
     * @param  array<int, array{content: string, depth: int, line: int, indent: int, blank?: bool}>  $lines
     */
    private function nextContentDepth(array $lines, int $from): int
    {
        $index = $this->nextContentIndex($lines, $from);

        return $index === null ? -1 : $lines[$index]['depth'];
    }

    private function findUnquotedColon(string $line): ?int
    {
        $position = HeaderParser::findUnquoted($line, Constants::COLON);

        return $position === false ? null : $position;
    }
}
