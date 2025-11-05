<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

final class FormatInvariantsTest extends TestCase
{
    public function test_no_trailing_spaces_on_lines(): void
    {
        $input = [
            'user' => [
                'id' => 123,
                'name' => 'Ada',
                'tags' => ['a', 'b'],
            ],
        ];
        $encoded = Toon::encode($input);
        $lines = explode("\n", $encoded);

        foreach ($lines as $line) {
            $this->assertSame($line, rtrim($line), "Line should not have trailing spaces: '{$line}'");
        }
    }

    public function test_no_trailing_newline_at_end(): void
    {
        $input = ['id' => 123, 'name' => 'Ada'];
        $encoded = Toon::encode($input);
        $this->assertStringEndsNotWith("\n", $encoded, 'Output should not end with newline');
    }

    public function test_consistent_formatting_with_multiple_encodes(): void
    {
        $input = ['id' => 123, 'name' => 'Ada', 'active' => true];

        $first = Toon::encode($input);
        $second = Toon::encode($input);
        $third = Toon::encode($input);

        $this->assertEquals($first, $second);
        $this->assertEquals($second, $third);
    }

    public function test_lines_properly_terminated(): void
    {
        $input = ['a' => 1, 'b' => 2, 'c' => 3];
        $encoded = Toon::encode($input);

        // All newlines should be \n (not \r\n or \r)
        $this->assertStringNotContainsString("\r\n", $encoded);
        $this->assertStringNotContainsString("\r", $encoded);
    }

    public function test_indentation_uses_spaces_not_tabs(): void
    {
        $input = ['a' => ['b' => ['c' => 'd']]];
        $encoded = Toon::encode($input);

        // Check that indentation is spaces
        $lines = explode("\n", $encoded);
        foreach ($lines as $line) {
            if (preg_match('/^(\s+)/', $line, $matches)) {
                $indent = $matches[1];
                // Ensure it's only spaces, not tabs
                $this->assertSame(str_replace("\t", '', $indent), $indent, 'Indentation should use spaces, not tabs');
            }
        }
    }

    public function test_empty_object_output(): void
    {
        $empty = [];
        $encoded = Toon::encode($empty);
        $this->assertEquals('', $encoded, 'Empty object should produce empty string');
    }

    public function test_single_value_on_single_line(): void
    {
        $input = 'hello';
        $encoded = Toon::encode($input);
        $this->assertStringNotContainsString("\n", $encoded, 'Single value should not contain newlines');
    }

    public function test_array_elements_separated_correctly(): void
    {
        $input = ['items' => ['a', 'b', 'c']];
        $encoded = Toon::encode($input);
        // Should be separated by commas
        $this->assertStringContainsString('a,b,c', $encoded);
    }

    public function test_object_properties_on_separate_lines(): void
    {
        $input = ['a' => 1, 'b' => 2, 'c' => 3];
        $encoded = Toon::encode($input);
        $lines = explode("\n", $encoded);
        $this->assertCount(3, $lines, 'Object with 3 properties should have 3 lines');
    }

    public function test_nested_indentation_is_consistent(): void
    {
        $input = ['a' => ['b' => ['c' => 'd']]];
        $encoded = Toon::encode($input);
        $lines = explode("\n", $encoded);

        // Line 0: no indent
        // Line 1: 2 spaces
        // Line 2: 4 spaces
        $this->assertSame('a:', $lines[0]);
        $this->assertStringStartsWith('  ', $lines[1]);
        $this->assertStringStartsWith('    ', $lines[2]);
    }

    public function test_tabular_header_format(): void
    {
        $input = [
            'items' => [
                ['id' => 1, 'name' => 'a'],
                ['id' => 2, 'name' => 'b'],
            ],
        ];
        $encoded = Toon::encode($input);
        // Should have format: items[2]{id,name}:
        $this->assertStringContainsString('[2]{id,name}:', $encoded);
    }

    public function test_list_item_marker_format(): void
    {
        $input = [
            'items' => [
                ['id' => 1, 'extra' => true],
                ['id' => 2],
            ],
        ];
        $encoded = Toon::encode($input);
        // Should have list markers
        $this->assertStringContainsString('- id:', $encoded);
    }

    public function test_colon_space_separation(): void
    {
        $input = ['key' => 'value'];
        $encoded = Toon::encode($input);
        $this->assertEquals('key: value', $encoded);
        $this->assertStringNotContainsString('key:value', $encoded);
        $this->assertStringNotContainsString('key :value', $encoded);
    }

    public function test_no_extra_whitespace_in_arrays(): void
    {
        $input = ['items' => [1, 2, 3]];
        $encoded = Toon::encode($input);
        // Should be "1,2,3" not "1, 2, 3" or "1 ,2 ,3"
        $this->assertStringContainsString('1,2,3', $encoded);
        $this->assertStringNotContainsString('1, 2', $encoded);
        $this->assertStringNotContainsString('1 ,2', $encoded);
    }

    // Phase 2.3: Header spacing tests

    public function test_header_has_exactly_one_space_after_colon(): void
    {
        // Headers with array length should have exactly one space after colon
        $input = ['items' => ['a', 'b', 'c']];
        $encoded = Toon::encode($input);
        // Format should be "items[3]: a,b,c" with single space after colon
        $this->assertStringContainsString('[3]: ', $encoded);
        $this->assertStringNotContainsString('[3]:  ', $encoded); // no double space
        $this->assertStringNotContainsString('[3]:a', $encoded); // no missing space
    }

    public function test_header_no_multiple_spaces_after_colon(): void
    {
        // Verify that headers don't have multiple spaces after colon
        $input = ['items' => ['a', 'b', 'c']];
        $encoded = Toon::encode($input);
        $lines = explode("\n", $encoded);
        // Check each line for colon spacing
        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                // Should have single space after colon, not double
                $this->assertStringNotContainsString(':  ', $line, "Line should not have double space after colon: '{$line}'");
            }
        }
    }

    // Phase 4.2: Negative Tests

    public function test_encode_never_produces_double_spaces(): void
    {
        // Verify no double spaces appear in content (excluding indentation)
        $testCases = [
            // Simple object
            ['key' => 'value', 'another' => 'data'],
            // Arrays with tabular format
            [
                'users' => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                ],
            ],
            // List format
            [
                'items' => [
                    ['a' => 1],
                    ['b' => 2, 'c' => 3],
                ],
            ],
            // Inline arrays
            ['numbers' => [1, 2, 3, 4, 5]],
            // Complex mixed structure
            [
                'data' => [
                    'primitives' => [1, 2, 3],
                    'nested' => [
                        'key' => 'value',
                    ],
                ],
            ],
        ];

        foreach ($testCases as $input) {
            $result = Toon::encode($input);
            $lines = explode("\n", $result);

            // Check each line after stripping leading indentation
            foreach ($lines as $line) {
                $trimmedLine = ltrim($line);
                // After removing indentation, there should be no double spaces
                $this->assertStringNotContainsString('  ', $trimmedLine, "Line content should not have double spaces: '{$trimmedLine}'");
            }
        }
    }

    public function test_encode_never_produces_trailing_commas(): void
    {
        // Verify no trailing commas in any array format
        $testCases = [
            // Inline primitive array
            ['items' => [1, 2, 3]],
            // Tabular array
            [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ],
            // Array of arrays
            [
                'nested' => [
                    ['a', 'b'],
                    ['c', 'd'],
                ],
            ],
            // Single element arrays
            ['single' => [42]],
            // Empty arrays
            ['empty' => []],
            // Mixed content
            [
                'data' => [
                    'list' => ['a', 'b', 'c'],
                    'objects' => [
                        ['x' => 1],
                        ['x' => 2],
                    ],
                ],
            ],
        ];

        foreach ($testCases as $input) {
            $result = Toon::encode($input);

            // Check for trailing comma patterns
            $this->assertStringNotContainsString(',]', $result, 'No trailing comma before ]');
            $this->assertStringNotContainsString(',}', $result, 'No trailing comma before }');
            $this->assertStringNotContainsString(', ', $result, 'No comma followed by space in arrays');

            // Check end of lines don't have trailing commas
            $lines = explode("\n", $result);
            foreach ($lines as $line) {
                $trimmed = rtrim($line);
                if ($trimmed !== '') {
                    $this->assertStringEndsNotWith(',', $trimmed, "Line should not end with comma: '{$line}'");
                }
            }
        }
    }

    public function test_encode_never_uses_mixed_delimiters_in_same_context(): void
    {
        // Verify delimiter consistency within the same array/context
        $testCases = [
            // Test with comma delimiter (default)
            [
                'data' => ['items' => [1, 2, 3]],
                'options' => null,
                'delimiter' => ',',
            ],
            // Test with pipe delimiter
            [
                'data' => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                ],
                'options' => new \HelgeSverre\Toon\EncodeOptions(delimiter: '|'),
                'delimiter' => '|',
            ],
            // Test with tab delimiter
            [
                'data' => ['values' => ['a', 'b', 'c']],
                'options' => new \HelgeSverre\Toon\EncodeOptions(delimiter: "\t"),
                'delimiter' => "\t",
            ],
        ];

        foreach ($testCases as $testCase) {
            $result = Toon::encode($testCase['data'], $testCase['options']);

            // For comma delimiter, ensure no pipes or tabs in array contexts
            if ($testCase['delimiter'] === ',') {
                // Check inline arrays don't mix delimiters
                if (preg_match('/\[[\d#]+\]: (.+)/', $result, $matches)) {
                    $arrayContent = $matches[1];
                    $this->assertStringNotContainsString('|', $arrayContent, 'Comma-delimited array should not contain pipes');
                    $this->assertStringNotContainsString("\t", $arrayContent, 'Comma-delimited array should not contain tabs');
                }
            }

            // For pipe delimiter, ensure no commas in array contexts
            if ($testCase['delimiter'] === '|') {
                // Check that array data uses pipes consistently
                if (preg_match('/\[[\d#]+[|]?\]: (.+)/', $result, $matches)) {
                    $arrayContent = $matches[1];
                    // Pipes should be present, commas should not be delimiters
                    if (str_contains($arrayContent, '|')) {
                        // If pipes are used, commas should not be delimiters
                        $parts = explode('|', $arrayContent);
                        foreach ($parts as $part) {
                            // Each part should not have comma as delimiter
                            $this->assertTrue(
                                ! str_contains(trim($part), ',') || count($parts) === 1,
                                'Pipe-delimited array should not mix comma delimiters'
                            );
                        }
                    }
                }
            }

            // For tab delimiter, ensure no commas or pipes in array contexts
            if ($testCase['delimiter'] === "\t") {
                if (preg_match('/\[[\d#]+\t?\]: (.+)/', $result, $matches)) {
                    $arrayContent = $matches[1];
                    if (str_contains($arrayContent, "\t")) {
                        $this->assertStringNotContainsString('|', $arrayContent, 'Tab-delimited array should not contain pipes');
                    }
                }
            }
        }
    }
}
