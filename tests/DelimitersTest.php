<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

final class DelimitersTest extends TestCase
{
    // Tab Delimiter Tests
    public function test_encode_with_tab_delimiter_primitive_array(): void
    {
        $input = ['tags' => ['reading', 'gaming', 'coding']];
        $options = new EncodeOptions(delimiter: "\t");
        $expected = "tags[3\\t]: reading\tgaming\tcoding";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_encode_with_tab_delimiter_tabular_array(): void
    {
        $input = [
            'items' => [
                ['sku' => 'A1', 'qty' => 2, 'price' => 9.99],
                ['sku' => 'B2', 'qty' => 1, 'price' => 14.5],
            ],
        ];
        $options = new EncodeOptions(delimiter: "\t");
        $expected = "items[2\\t]{sku\tqty\tprice}:\n  A1\t2\t9.99\n  B2\t1\t14.5";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_encode_with_tab_delimiter_nested_arrays(): void
    {
        $input = ['pairs' => [['a', 'b'], ['c', 'd']]];
        $options = new EncodeOptions(delimiter: "\t");
        $expected = "pairs[2\\t]:\n  - [2\\t]: a\tb\n  - [2\\t]: c\td";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_encode_with_tab_delimiter_root_array(): void
    {
        $input = ['x', 'y', 'z'];
        $options = new EncodeOptions(delimiter: "\t");
        $expected = "[3\\t]: x\ty\tz";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_encode_with_tab_delimiter_root_object_array(): void
    {
        $input = [['id' => 1], ['id' => 2]];
        $options = new EncodeOptions(delimiter: "\t");
        $expected = "[2\\t]{id}:\n  1\n  2";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    // Pipe Delimiter Tests
    public function test_encode_with_pipe_delimiter_primitive_array(): void
    {
        $input = ['tags' => ['reading', 'gaming', 'coding']];
        $options = new EncodeOptions(delimiter: '|');
        $expected = 'tags[3|]: reading|gaming|coding';
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_encode_with_pipe_delimiter_tabular_array(): void
    {
        $input = [
            'items' => [
                ['sku' => 'A1', 'qty' => 2, 'price' => 9.99],
                ['sku' => 'B2', 'qty' => 1, 'price' => 14.5],
            ],
        ];
        $options = new EncodeOptions(delimiter: '|');
        $expected = "items[2|]{sku|qty|price}:\n  A1|2|9.99\n  B2|1|14.5";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_encode_with_pipe_delimiter_nested_arrays(): void
    {
        $input = ['pairs' => [['a', 'b'], ['c', 'd']]];
        $options = new EncodeOptions(delimiter: '|');
        $expected = "pairs[2|]:\n  - [2|]: a|b\n  - [2|]: c|d";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_encode_with_pipe_delimiter_root_array(): void
    {
        $input = ['x', 'y', 'z'];
        $options = new EncodeOptions(delimiter: '|');
        $expected = '[3|]: x|y|z';
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_encode_with_pipe_delimiter_root_object_array(): void
    {
        $input = [['id' => 1], ['id' => 2]];
        $options = new EncodeOptions(delimiter: '|');
        $expected = "[2|]{id}:\n  1\n  2";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    // Delimiter-Aware Quoting Tests
    public function test_tab_delimiter_quotes_values_with_tabs(): void
    {
        $input = ['items' => ['a', "b\tc", 'd']];
        $options = new EncodeOptions(delimiter: "\t");
        $expected = "items[3\\t]: a\t\"b\\tc\"\td";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_pipe_delimiter_quotes_values_with_pipes(): void
    {
        $input = ['items' => ['a', 'b|c', 'd']];
        $options = new EncodeOptions(delimiter: '|');
        $expected = 'items[3|]: a|"b|c"|d';
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_tab_delimiter_does_not_quote_commas(): void
    {
        $input = ['items' => ['a,b', 'c,d']];
        $options = new EncodeOptions(delimiter: "\t");
        $expected = "items[2\\t]: a,b\tc,d";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_pipe_delimiter_does_not_quote_commas(): void
    {
        $input = ['items' => ['a,b', 'c,d']];
        $options = new EncodeOptions(delimiter: '|');
        $expected = 'items[2|]: a,b|c,d';
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_comma_delimiter_quotes_commas_in_tabular(): void
    {
        $input = [
            'items' => [
                ['id' => 1, 'note' => 'a,b'],
                ['id' => 2, 'note' => 'c,d'],
            ],
        ];
        $expected = "items[2]{id,note}:\n  1,\"a,b\"\n  2,\"c,d\"";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_pipe_delimiter_does_not_quote_commas_in_values(): void
    {
        $input = ['note' => 'a,b'];
        $options = new EncodeOptions(delimiter: '|');
        $expected = 'note: a,b';
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    // Length Marker Tests
    public function test_encode_with_length_marker(): void
    {
        $input = ['tags' => ['reading', 'gaming', 'coding']];
        $options = new EncodeOptions(lengthMarker: '#');
        $expected = 'tags[#3]: reading,gaming,coding';
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_encode_with_length_marker_empty_array(): void
    {
        $input = ['items' => []];
        $options = new EncodeOptions(lengthMarker: '#');
        // Empty arrays are treated as empty objects in PHP
        $expected = 'items:';
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_encode_with_length_marker_tabular(): void
    {
        $input = [
            'items' => [
                ['sku' => 'A1', 'qty' => 2, 'price' => 9.99],
                ['sku' => 'B2', 'qty' => 1, 'price' => 14.5],
            ],
        ];
        $options = new EncodeOptions(lengthMarker: '#');
        $expected = "items[#2]{sku,qty,price}:\n  A1,2,9.99\n  B2,1,14.5";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_encode_with_length_marker_nested(): void
    {
        $input = ['pairs' => [['a', 'b'], ['c', 'd']]];
        $options = new EncodeOptions(lengthMarker: '#');
        $expected = "pairs[#2]:\n  - [#2]: a,b\n  - [#2]: c,d";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_encode_with_length_marker_and_pipe_delimiter(): void
    {
        $input = ['tags' => ['reading', 'gaming', 'coding']];
        $options = new EncodeOptions(lengthMarker: '#', delimiter: '|');
        $expected = 'tags[#3|]: reading|gaming|coding';
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_encode_without_length_marker_default(): void
    {
        $input = ['tags' => ['reading', 'gaming', 'coding']];
        $expected = 'tags[3]: reading,gaming,coding';
        $this->assertEquals($expected, Toon::encode($input));
    }
}
