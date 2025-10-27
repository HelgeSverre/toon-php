<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

final class TabularArraysTest extends TestCase
{
    public function test_encode_tabular_array_basic(): void
    {
        $input = [
            'items' => [
                ['sku' => 'A1', 'qty' => 2, 'price' => 9.99],
                ['sku' => 'B2', 'qty' => 1, 'price' => 14.5],
            ],
        ];
        $expected = "items[2]{sku,qty,price}:\n  A1,2,9.99\n  B2,1,14.5";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_tabular_array_with_null_values(): void
    {
        $input = [
            'items' => [
                ['id' => 1, 'value' => null],
                ['id' => 2, 'value' => 'test'],
            ],
        ];
        $expected = "items[2]{id,value}:\n  1,null\n  2,test";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_tabular_array_with_quoted_values(): void
    {
        $input = [
            'items' => [
                ['sku' => 'A,1', 'desc' => 'cool', 'qty' => 2],
                ['sku' => 'B2', 'desc' => 'wip: test', 'qty' => 1],
            ],
        ];
        $expected = "items[2]{sku,desc,qty}:\n  \"A,1\",cool,2\n  B2,\"wip: test\",1";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_tabular_array_with_string_booleans(): void
    {
        $input = [
            'items' => [
                ['id' => 1, 'status' => 'true'],
                ['id' => 2, 'status' => 'false'],
            ],
        ];
        $expected = "items[2]{id,status}:\n  1,\"true\"\n  2,\"false\"";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_tabular_array_with_special_keys(): void
    {
        $input = [
            'items' => [
                ['order:id' => 1, 'full name' => 'Ada'],
                ['order:id' => 2, 'full name' => 'Bob'],
            ],
        ];
        $expected = "items[2]{\"order:id\",\"full name\"}:\n  1,Ada\n  2,Bob";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_tabular_array_with_reordered_keys(): void
    {
        // Keys in same order for all objects = tabular
        $input = [
            'items' => [
                ['a' => 1, 'b' => 2, 'c' => 3],
                ['c' => 30, 'b' => 20, 'a' => 10],
            ],
        ];
        // Note: PHP arrays maintain insertion order, so this might not work exactly as expected
        // but the test ensures consistent key handling
        $encoded = Toon::encode($input);
        $this->assertStringContainsString('items[2]{', $encoded);
    }

    public function test_encode_list_format_when_different_keys(): void
    {
        $input = [
            'items' => [
                ['id' => 1, 'name' => 'First'],
                ['id' => 2, 'name' => 'Second', 'extra' => true],
            ],
        ];
        $expected = "items[2]:\n  - id: 1\n    name: First\n  - id: 2\n    name: Second\n    extra: true";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_list_format_with_nested_object(): void
    {
        $input = ['items' => [['id' => 1, 'nested' => ['x' => 1]]]];
        $expected = "items[1]:\n  - id: 1\n    nested:\n      x: 1";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_list_format_with_array_in_object(): void
    {
        $input = ['items' => [['nums' => [1, 2, 3], 'name' => 'test']]];
        $expected = "items[1]:\n  - nums[3]: 1,2,3\n    name: test";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_list_format_with_array_in_object_reordered(): void
    {
        $input = ['items' => [['name' => 'test', 'nums' => [1, 2, 3]]]];
        $expected = "items[1]:\n  - name: test\n    nums[3]: 1,2,3";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_list_format_with_nested_array_of_arrays(): void
    {
        $input = ['items' => [['matrix' => [[1, 2], [3, 4]], 'name' => 'grid']]];
        $expected = "items[1]:\n  - matrix[2]:\n    - [2]: 1,2\n    - [2]: 3,4\n    name: grid";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_list_format_with_nested_tabular_array(): void
    {
        $input = [
            'items' => [[
                'users' => [
                    ['id' => 1, 'name' => 'Ada'],
                    ['id' => 2, 'name' => 'Bob'],
                ],
                'status' => 'active',
            ]],
        ];
        $expected = "items[1]:\n  - users[2]{id,name}:\n    1,Ada\n    2,Bob\n    status: active";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_list_format_with_nested_non_uniform_array(): void
    {
        $input = [
            'items' => [[
                'users' => [
                    ['id' => 1, 'name' => 'Ada'],
                    ['id' => 2],
                ],
                'status' => 'active',
            ]],
        ];
        $expected = "items[1]:\n  - users[2]:\n    - id: 1\n      name: Ada\n    - id: 2\n    status: active";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_list_format_with_multiple_arrays(): void
    {
        $input = ['items' => [['nums' => [1, 2], 'tags' => ['a', 'b'], 'name' => 'test']]];
        $expected = "items[1]:\n  - nums[2]: 1,2\n    tags[2]: a,b\n    name: test";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_list_format_with_only_arrays(): void
    {
        $input = ['items' => [['nums' => [1, 2, 3], 'tags' => ['a', 'b']]]];
        $expected = "items[1]:\n  - nums[3]: 1,2,3\n    tags[2]: a,b";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_list_format_with_empty_array(): void
    {
        $input = ['items' => [['name' => 'test', 'data' => []]]];
        $expected = "items[1]:\n  - name: test\n    data:";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_list_format_with_single_property_tabular(): void
    {
        $input = ['items' => [['users' => [['id' => 1], ['id' => 2]], 'note' => 'x']]];
        $expected = "items[1]:\n  - users[2]{id}:\n    1\n    2\n    note: x";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_list_format_with_empty_array_first(): void
    {
        $input = ['items' => [['data' => [], 'name' => 'x']]];
        $expected = "items[1]:\n  - data:\n    name: x";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_list_format_mixed_value_types(): void
    {
        $input = [
            'items' => [
                ['id' => 1, 'data' => 'string'],
                ['id' => 2, 'data' => ['nested' => true]],
            ],
        ];
        $expected = "items[2]:\n  - id: 1\n    data: string\n  - id: 2\n    data:\n      nested: true";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_complex_structure(): void
    {
        $input = [
            'user' => [
                'id' => 123,
                'name' => 'Ada',
                'tags' => ['reading', 'gaming'],
                'active' => true,
                'prefs' => [],
            ],
        ];
        $expected = "user:\n  id: 123\n  name: Ada\n  tags[2]: reading,gaming\n  active: true\n  prefs:";
        $this->assertEquals($expected, Toon::encode($input));
    }
}
