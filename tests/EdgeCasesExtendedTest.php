<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

final class EdgeCasesExtendedTest extends TestCase
{
    public function test_encoding_with_pipe_delimiter(): void
    {
        $data = [
            ['a', 'b', 'c'],
            ['d', 'e', 'f'],
        ];

        $result = Toon::encode($data, new EncodeOptions(
            delimiter: '|'
        ));

        $this->assertStringContainsString('[2|]:', $result);
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
        $this->assertStringContainsString('inner[0]:', $result);
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
        $this->assertStringContainsString('level3[0]:', $result);
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

    public function test_root_level_primitive_array(): void
    {
        $data = [1, 2, 3, 4, 5];

        $result = Toon::encode($data);
        $expected = '[5]: 1,2,3,4,5';
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

    public function test_combining_tab_delimiter(): void
    {
        $data = [
            'items' => [
                ['a', 'b'],
                ['c', 'd'],
            ],
        ];

        $options = new EncodeOptions(
            delimiter: "\t"
        );

        $result = Toon::encode($data, $options);
        $this->assertStringContainsString("[2\t]:", $result);
        $this->assertStringContainsString("a\tb", $result);
    }

    public function test_tabular_format_with_pipe_delimiter(): void
    {
        $data = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];

        $options = new EncodeOptions(
            delimiter: '|'
        );

        $result = Toon::encode($data, $options);
        $this->assertStringContainsString('[2|]{id|name}:', $result);
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

    public function test_encode_full_floating_point_precision(): void
    {
        // P1 High Priority: Test that full floating point precision is preserved
        // TOON preserves full PHP float precision (16 decimal places for 1/3)
        $value = 1 / 3;
        $result = Toon::encode($value);

        // Verify high precision is preserved (16 decimal places = 18 chars total)
        $this->assertIsString($result);
        $this->assertEquals('0.3333333333333333', $result);

        // Also test with another precision value
        $value2 = 1 / 7;
        $result2 = Toon::encode($value2);
        $this->assertStringStartsWith('0.142857', $result2);
        $this->assertGreaterThanOrEqual(15, strlen($result2));
    }

    // Phase 2.1: Float Precision Validation

    public function test_encode_preserves_float_precision_in_string(): void
    {
        // Verify that precision is maintained in the string representation
        // Test various significant digit scenarios
        $result1 = Toon::encode(\M_PI);
        $this->assertEquals('3.141592653589793', $result1);

        $result2 = Toon::encode(\M_E);
        $this->assertEquals('2.718281828459045', $result2);

        $result3 = Toon::encode(sqrt(2));
        $this->assertEquals('1.4142135623730951', $result3);
    }

    public function test_encode_float_precision_15_significant_digits(): void
    {
        // Spec requires sufficient precision for round-trip fidelity
        // IEEE 754 double precision provides 15-17 significant digits
        $value = 1.23456789012345;  // 15 significant digits
        $result = Toon::encode($value);

        // Should preserve all digits
        $this->assertStringContainsString('1.23456789012345', $result);
        $this->assertIsNumeric($result);

        // Verify no scientific notation
        $this->assertStringNotContainsString('e', strtolower($result));
        $this->assertStringNotContainsString('E', $result);
    }

    public function test_encode_does_not_truncate_precision(): void
    {
        // Test that precision isn't artificially limited
        // Classic floating point quirk
        $value1 = 0.1 + 0.2;
        $result1 = Toon::encode($value1);
        $this->assertEquals('0.30000000000000004', $result1);

        // Beyond precision limit
        $value2 = 0.123456789123456789;
        $result2 = Toon::encode($value2);
        $this->assertEquals('0.12345678912345678', $result2);

        // Large number with decimals (within representable range)
        $value3 = 9999999999.123456;
        $result3 = Toon::encode($value3);
        $this->assertStringStartsWith('9999999999.123', $result3);
    }

    public function test_encode_repeating_decimals_precision(): void
    {
        // Test various repeating decimal scenarios
        $value1 = 1 / 3;
        $result1 = Toon::encode($value1);
        $this->assertEquals('0.3333333333333333', $result1);
        $this->assertGreaterThanOrEqual(17, strlen($result1)); // "0." + 15+ digits

        $value2 = 2 / 3;
        $result2 = Toon::encode($value2);
        $this->assertEquals('0.6666666666666666', $result2);

        $value3 = 1 / 6;
        $result3 = Toon::encode($value3);
        $this->assertEquals('0.16666666666666666', $result3);

        $value4 = 5 / 7;
        $result4 = Toon::encode($value4);
        $this->assertEquals('0.7142857142857143', $result4);
    }

    // Phase 4.1: Boundary Values

    public function test_encode_php_int_max_and_min(): void
    {
        // Test PHP integer boundaries (platform-dependent)
        $data = [
            'max' => PHP_INT_MAX,
            'min' => PHP_INT_MIN,
        ];

        $result = Toon::encode($data);

        // Verify max value is encoded correctly
        $this->assertStringContainsString('max: '.PHP_INT_MAX, $result);
        // Verify min value is encoded correctly (negative)
        $this->assertStringContainsString('min: '.PHP_INT_MIN, $result);

        // Ensure no scientific notation for integers
        $this->assertStringNotContainsString('e', strtolower($result));
        $this->assertStringNotContainsString('E', $result);
    }

    public function test_encode_php_float_epsilon(): void
    {
        // Test smallest representable positive float difference
        $data = [
            'epsilon' => PHP_FLOAT_EPSILON,
            'one_plus_epsilon' => 1.0 + PHP_FLOAT_EPSILON,
        ];

        $result = Toon::encode($data);

        // Verify epsilon is preserved with full precision
        $this->assertStringContainsString('epsilon:', $result);
        // PHP_FLOAT_EPSILON is approximately 2.220446049250313e-16
        // TOON encodes this in decimal notation with full precision
        $this->assertIsString($result);
        $this->assertStringContainsString('0.00000000000000022204', $result);

        // Verify 1.0 + epsilon shows the precision difference
        $this->assertStringContainsString('one_plus_epsilon: 1.0000000000000002', $result);
    }

    public function test_encode_subnormal_floats(): void
    {
        // Test very small floats near zero (subnormal/denormal numbers)
        // PHP converts these extremely small values to 0 during normalization
        $data = [
            'tiny1' => 1.0e-308,  // Near smallest normal float
            'tiny2' => 5.0e-324,  // Smallest positive subnormal
            'tiny3' => PHP_FLOAT_MIN, // PHP constant for smallest positive normalized float
        ];

        $result = Toon::encode($data);

        // Verify keys are present
        $this->assertStringContainsString('tiny1:', $result);
        $this->assertStringContainsString('tiny2:', $result);
        $this->assertStringContainsString('tiny3:', $result);

        // These subnormal values are so small they underflow to 0 in PHP
        // Verify they encode as 0
        $this->assertStringContainsString('tiny1: 0', $result);
        $this->assertStringContainsString('tiny2: 0', $result);
        $this->assertStringContainsString('tiny3: 0', $result);
    }

    public function test_encode_object_with_reserved_php_keywords_as_keys(): void
    {
        // Test object keys that are reserved PHP keywords
        $data = [
            'class' => 'MyClass',
            'function' => 'myFunc',
            'return' => 'value',
            'if' => 'condition',
            'else' => 'alternative',
            'while' => 'loop',
            'foreach' => 'iteration',
            'namespace' => 'App',
            'use' => 'import',
            'trait' => 'Serializable',
        ];

        $result = Toon::encode($data);

        // PHP keywords should be treated as normal identifiers in TOON
        // They don't need quoting since they're used as keys, not PHP code
        $this->assertStringContainsString('class: MyClass', $result);
        $this->assertStringContainsString('function: myFunc', $result);
        $this->assertStringContainsString('return: value', $result);
        $this->assertStringContainsString('if: condition', $result);
        $this->assertStringContainsString('namespace: App', $result);
    }

    public function test_encode_object_with_toon_syntax_tokens_as_keys(): void
    {
        // Test object keys that are TOON reserved words/syntax tokens
        $data = [
            'null' => 'not null',
            'true' => 'boolean true',
            'false' => 'boolean false',
        ];

        $result = Toon::encode($data);

        // In TOON, reserved words as object keys are allowed without quoting
        // because the context (key position) makes them unambiguous
        $this->assertStringContainsString('null: not null', $result);
        $this->assertStringContainsString('true: boolean true', $result);
        $this->assertStringContainsString('false: boolean false', $result);

        // Verify the values themselves are unquoted strings
        $this->assertStringNotContainsString('"not null"', $result);
        $this->assertStringNotContainsString('"boolean true"', $result);
        $this->assertStringNotContainsString('"boolean false"', $result);

        // This tests that TOON correctly handles reserved words in key positions
        // The key context provides sufficient disambiguation
    }
}
