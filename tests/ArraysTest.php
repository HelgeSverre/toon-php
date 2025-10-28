<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

final class ArraysTest extends TestCase
{
    public function test_encode_primitive_array(): void
    {
        $input = ['tags' => ['reading', 'gaming']];
        $expected = 'tags[2]: reading,gaming';
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_number_array(): void
    {
        $input = ['nums' => [1, 2, 3]];
        $expected = 'nums[3]: 1,2,3';
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_mixed_primitive_array(): void
    {
        $input = ['data' => ['x', 'y', true, 10]];
        $expected = 'data[4]: x,y,true,10';
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_empty_array(): void
    {
        $input = ['items' => []];
        $expected = 'items[0]:';
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_array_with_empty_string(): void
    {
        $input = ['items' => ['']];
        $expected = 'items[1]: ""';
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_array_with_mixed_empty_strings(): void
    {
        $input = ['items' => ['a', '', 'b']];
        $expected = 'items[3]: a,"",b';
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_array_with_spaces(): void
    {
        $input = ['items' => [' ', '  ']];
        $expected = 'items[2]: " ","  "';
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_array_with_values_containing_delimiters(): void
    {
        $input = ['items' => ['a', 'b,c', 'd:e']];
        $expected = 'items[3]: a,"b,c","d:e"';
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_array_with_string_primitives(): void
    {
        $input = ['items' => ['x', 'true', '42', '-3.14']];
        $expected = 'items[4]: x,"true","42","-3.14"';
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_array_with_structural_patterns(): void
    {
        $input = ['items' => ['[5]', '- item', '{key}']];
        $expected = 'items[3]: "[5]","- item","{key}"';
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_nested_arrays(): void
    {
        $input = ['pairs' => [['a', 'b'], ['c', 'd']]];
        $expected = "pairs[2]:\n  - [2]: a,b\n  - [2]: c,d";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_nested_arrays_with_quotes(): void
    {
        $input = ['pairs' => [['a', 'b'], ['c,d', 'e:f', 'true']]];
        $expected = "pairs[2]:\n  - [2]: a,b\n  - [3]: \"c,d\",\"e:f\",\"true\"";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_nested_arrays_with_empty_arrays(): void
    {
        $input = ['pairs' => [[], []]];
        $expected = "pairs[2]:\n  - [0]:\n  - [0]:";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_nested_arrays_with_varying_lengths(): void
    {
        $input = ['pairs' => [[1], [2, 3]]];
        $expected = "pairs[2]:\n  - [1]: 1\n  - [2]: 2,3";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_root_primitive_array(): void
    {
        $input = ['x', 'y', 'true', true, 10];
        $expected = '[5]: x,y,"true",true,10';
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_root_object_array_tabular(): void
    {
        $input = [['id' => 1], ['id' => 2]];
        $expected = "[2]{id}:\n  1\n  2";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_root_object_array_list(): void
    {
        $input = [['id' => 1], ['id' => 2, 'name' => 'Ada']];
        $expected = "[2]:\n  - id: 1\n  - id: 2\n    name: Ada";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_root_empty_array(): void
    {
        // In PHP, empty arrays are treated as empty objects
        $this->assertEquals('', Toon::encode([]));
    }

    public function test_encode_root_nested_arrays(): void
    {
        $input = [[1, 2], []];
        $expected = "[2]:\n  - [2]: 1,2\n  - [0]:";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_mixed_array(): void
    {
        $input = ['items' => [1, ['a' => 1], 'text']];
        $expected = "items[3]:\n  - 1\n  - a: 1\n  - text";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_mixed_array_with_object_and_array(): void
    {
        $input = ['items' => [['a' => 1], [1, 2]]];
        $expected = "items[2]:\n  - a: 1\n  - [2]: 1,2";
        $this->assertEquals($expected, Toon::encode($input));
    }
}
