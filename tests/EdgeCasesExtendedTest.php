<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

final class EdgeCasesExtendedTest extends TestCase
{
    public function test_encoding_with_all_delimiter_types_and_length_markers(): void
    {
        $data = [
            ['a', 'b', 'c'],
            ['d', 'e', 'f'],
        ];

        $result = Toon::encode($data, new EncodeOptions(
            delimiter: '|',
            lengthMarker: '#'
        ));

        $this->assertStringContainsString('[#2|]:', $result);
        $this->assertStringContainsString('a|b|c', $result);
    }

    public function test_empty_object_in_nested_structures(): void
    {
        $data = [
            'outer' => [
                'inner' => [],
            ],
        ];

        $result = Toon::encode($data);
        $this->assertStringContainsString('inner:', $result);
    }

    public function test_deeply_nested_empty_objects(): void
    {
        $data = [
            'level1' => [
                'level2' => [
                    'level3' => [],
                ],
            ],
        ];

        $result = Toon::encode($data);
        $this->assertStringContainsString('level3:', $result);
    }

    public function test_mixed_array_with_primitives_and_objects(): void
    {
        $data = [
            'mixed' => [
                'string',
                42,
                ['nested' => 'object'],
            ],
        ];

        $result = Toon::encode($data);
        $this->assertStringContainsString('mixed[3]:', $result);
        $this->assertStringContainsString('- string', $result);
        $this->assertStringContainsString('- 42', $result);
        $this->assertStringContainsString('nested: object', $result);
    }

    public function test_mixed_array_with_arrays_and_objects(): void
    {
        $data = [
            [1, 2],
            ['key' => 'value'],
            [3, 4],
        ];

        $result = Toon::encode($data);
        $this->assertStringContainsString('[3]:', $result);
    }

    public function test_array_with_all_null_values(): void
    {
        $data = ['nulls' => [null, null, null]];

        $result = Toon::encode($data);
        $expected = 'nulls[3]: null,null,null';
        $this->assertEquals($expected, $result);
    }

    public function test_array_with_all_boolean_values(): void
    {
        $data = ['bools' => [true, false, true]];

        $result = Toon::encode($data);
        $expected = 'bools[3]: true,false,true';
        $this->assertEquals($expected, $result);
    }

    public function test_nested_object_with_list_items(): void
    {
        $data = [
            'items' => [
                ['name' => 'Alice', 'age' => 30],
                ['name' => 'Bob', 'age' => 25],
            ],
        ];

        // This should use tabular format
        $result = Toon::encode($data);
        $this->assertStringContainsString('{name,age}:', $result);
    }

    public function test_object_array_with_different_keys_uses_list_format(): void
    {
        $data = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'email' => 'bob@example.com'],
        ];

        $result = Toon::encode($data);
        // Should use list format, not tabular
        $this->assertStringContainsString('- id:', $result);
        $this->assertStringContainsString('- id:', $result);
    }

    public function test_single_element_array_of_objects(): void
    {
        $data = [
            ['id' => 1, 'name' => 'Alice'],
        ];

        $result = Toon::encode($data);
        $this->assertStringContainsString('[1]{id,name}:', $result);
    }

    public function test_root_level_primitive_array_with_length_marker(): void
    {
        $data = [1, 2, 3, 4, 5];
        $options = new EncodeOptions(lengthMarker: '#');

        $result = Toon::encode($data, $options);
        $expected = '[#5]: 1,2,3,4,5';
        $this->assertEquals($expected, $result);
    }

    public function test_complex_nested_structure_with_multiple_array_types(): void
    {
        $data = [
            'primitives' => [1, 2, 3],
            'objects' => [
                ['id' => 1, 'value' => 'a'],
                ['id' => 2, 'value' => 'b'],
            ],
            'arrays' => [
                ['x', 'y'],
                ['z', 'w'],
            ],
            'mixed' => [
                1,
                ['key' => 'value'],
                [1, 2],
            ],
        ];

        $result = Toon::encode($data);
        $this->assertStringContainsString('primitives[3]:', $result);
        $this->assertStringContainsString('objects[2]{id,value}:', $result);
        $this->assertStringContainsString('arrays[2]:', $result);
        $this->assertStringContainsString('mixed[3]:', $result);
    }

    public function test_object_with_numeric_string_keys(): void
    {
        $data = [
            '0' => 'zero',
            '1' => 'one',
            'key' => 'value',
        ];

        $result = Toon::encode($data);
        // Numeric string keys should be quoted
        $this->assertStringContainsString('"0": zero', $result);
        $this->assertStringContainsString('"1": one', $result);
        $this->assertStringContainsString('key: value', $result);
    }

    public function test_empty_nested_arrays(): void
    {
        $data = [
            'nested' => [
                [],
                [],
            ],
        ];

        $result = Toon::encode($data);
        $this->assertStringContainsString('nested[2]:', $result);
    }

    public function test_array_of_empty_objects(): void
    {
        $data = [
            'items' => [
                [],
                [],
            ],
        ];

        $result = Toon::encode($data);
        $this->assertStringContainsString('items[2]:', $result);
    }

    public function test_object_with_array_containing_single_null(): void
    {
        $data = ['nulls' => [null]];

        $result = Toon::encode($data);
        $expected = 'nulls[1]: null';
        $this->assertEquals($expected, $result);
    }

    public function test_combining_length_markers_with_tab_delimiter(): void
    {
        $data = [
            'items' => [
                ['a', 'b'],
                ['c', 'd'],
            ],
        ];

        $options = new EncodeOptions(
            delimiter: "\t",
            lengthMarker: '#'
        );

        $result = Toon::encode($data, $options);
        $this->assertStringContainsString("[#2\t]:", $result);
        $this->assertStringContainsString("a\tb", $result);
    }

    public function test_tabular_format_with_pipe_delimiter_and_length_marker(): void
    {
        $data = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];

        $options = new EncodeOptions(
            delimiter: '|',
            lengthMarker: '#'
        );

        $result = Toon::encode($data, $options);
        $this->assertStringContainsString('[#2|]{id|name}:', $result);
        $this->assertStringContainsString('1|Alice', $result);
    }

    public function test_root_empty_array(): void
    {
        $data = [];

        $result = Toon::encode($data);
        // Empty array at root should produce nothing
        $this->assertEquals('', $result);
    }

    public function test_special_float_values_in_arrays(): void
    {
        $data = ['values' => [1.0, INF, -INF, NAN, 0.0]];

        $result = Toon::encode($data);
        $this->assertStringContainsString('null', $result); // INF, -INF, NAN become null
        $this->assertStringContainsString('1', $result);
        $this->assertStringContainsString('0', $result);
    }

    public function test_object_with_keys_requiring_quotes(): void
    {
        $data = [
            'normal-key' => 'value1',
            'key with spaces' => 'value2',
            'key:colon' => 'value3',
            'key[bracket' => 'value4',
        ];

        $result = Toon::encode($data);
        $this->assertStringContainsString('"key with spaces":', $result);
        $this->assertStringContainsString('"key:colon":', $result);
        $this->assertStringContainsString('"key[bracket":', $result);
    }

    public function test_nested_objects_at_multiple_depths(): void
    {
        $data = [
            'a' => [
                'b' => [
                    'c' => [
                        'd' => 'deep',
                    ],
                ],
            ],
        ];

        $result = Toon::encode($data);
        $this->assertStringContainsString('a:', $result);
        $this->assertStringContainsString('b:', $result);
        $this->assertStringContainsString('c:', $result);
        $this->assertStringContainsString('d: deep', $result);
    }

    public function test_array_of_objects_with_null_values(): void
    {
        $data = [
            ['id' => 1, 'value' => null],
            ['id' => 2, 'value' => null],
        ];

        $result = Toon::encode($data);
        $this->assertStringContainsString('[2]{id,value}:', $result);
        $this->assertStringContainsString('1,null', $result);
        $this->assertStringContainsString('2,null', $result);
    }

    public function test_custom_indent_with_nested_structures(): void
    {
        $data = [
            'parent' => [
                'child' => [
                    'value' => 123,
                ],
            ],
        ];

        $options = new EncodeOptions(indent: 4);
        $result = Toon::encode($data, $options);

        // Should have 4-space indents
        $this->assertStringContainsString('    child:', $result);
        $this->assertStringContainsString('        value: 123', $result);
    }

    public function test_zero_indent(): void
    {
        $data = [
            'parent' => [
                'child' => 'value',
            ],
        ];

        $options = new EncodeOptions(indent: 0);
        $result = Toon::encode($data, $options);

        // Should have no indentation
        $lines = explode("\n", $result);
        $this->assertEquals('parent:', $lines[0]);
        $this->assertEquals('child: value', $lines[1]);
    }

    public function test_large_indent(): void
    {
        $data = [
            'a' => [
                'b' => 'value',
            ],
        ];

        $options = new EncodeOptions(indent: 8);
        $result = Toon::encode($data, $options);

        $this->assertStringContainsString('        b: value', $result);
    }
}
