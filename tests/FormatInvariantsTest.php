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
}
