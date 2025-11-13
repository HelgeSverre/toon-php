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
        $expected = "tags[3\t]: reading\tgaming\tcoding";
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
        $expected = "items[2\t]{sku\tqty\tprice}:\n  A1\t2\t9.99\n  B2\t1\t14.5";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_encode_with_tab_delimiter_nested_arrays(): void
    {
        $input = ['pairs' => [['a', 'b'], ['c', 'd']]];
        $options = new EncodeOptions(delimiter: "\t");
        $expected = "pairs[2\t]:\n  - [2\t]: a\tb\n  - [2\t]: c\td";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_encode_with_tab_delimiter_root_array(): void
    {
        $input = ['x', 'y', 'z'];
        $options = new EncodeOptions(delimiter: "\t");
        $expected = "[3\t]: x\ty\tz";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_encode_with_tab_delimiter_root_object_array(): void
    {
        $input = [['id' => 1], ['id' => 2]];
        $options = new EncodeOptions(delimiter: "\t");
        $expected = "[2\t]{id}:\n  1\n  2";
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
        $expected = "items[3\t]: a\t\"b\\tc\"\td";
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
        $expected = "items[2\t]: a,b\tc,d";
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

    public function test_encode_without_length_marker_default(): void
    {
        $input = ['tags' => ['reading', 'gaming', 'coding']];
        $expected = 'tags[3]: reading,gaming,coding';
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_pipe_delimiter_in_array_of_arrays(): void
    {
        $input = [
            [1, 2, 3],
            [4, 5, 6],
        ];
        $options = new EncodeOptions(delimiter: '|');
        $expected = "[2|]:\n  - [3|]: 1|2|3\n  - [3|]: 4|5|6";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_tab_delimiter_in_array_of_arrays(): void
    {
        $input = [
            [1, 2, 3],
            [4, 5, 6],
        ];
        $options = new EncodeOptions(delimiter: "\t");
        $expected = "[2\t]:\n  - [3\t]: 1\t2\t3\n  - [3\t]: 4\t5\t6";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_comma_delimiter_has_no_delimiter_key(): void
    {
        $input = ['items' => [1, 2, 3]];
        $expected = 'items[3]: 1,2,3';
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_pipe_delimiter_with_empty_array_items(): void
    {
        $input = ['items' => [[], []]];
        $options = new EncodeOptions(delimiter: '|');
        $expected = "items[2|]:\n  - [0|]:\n  - [0|]:";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_tab_delimiter_with_mixed_content(): void
    {
        $input = [
            'name' => 'test',
            'items' => [1, 2, 3],
        ];
        $options = new EncodeOptions(delimiter: "\t");
        $expected = "name: test\nitems[3\t]: 1\t2\t3";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_pipe_delimiter_with_mixed_content(): void
    {
        $input = [
            'name' => 'test',
            'items' => [1, 2, 3],
        ];
        $options = new EncodeOptions(delimiter: '|');
        $expected = "name: test\nitems[3|]: 1|2|3";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_delimiter_with_single_item_array(): void
    {
        $input = ['items' => ['only']];
        $options = new EncodeOptions(delimiter: '|');
        $expected = 'items[1|]: only';
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_tab_delimiter_does_not_quote_commas_in_tabular_values(): void
    {
        // P0 Critical: When tab is delimiter, commas in tabular row values are not quoted
        // This tests delimiter context awareness - non-active delimiters should not trigger quoting
        $input = [
            ['id' => 1, 'note' => 'a,b'],
            ['id' => 2, 'note' => 'c,d'],
        ];
        $options = new EncodeOptions(delimiter: "\t");
        $expected = "[2\t]{id\tnote}:\n  1\ta,b\n  2\tc,d";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_ambiguous_strings_quoted_with_non_comma_delimiter(): void
    {
        // P0 Critical: Reserved words and numeric strings are quoted even with non-comma delimiters
        // Ambiguity rules are orthogonal to delimiter choice - this is a core spec requirement
        $input = ['items' => ['true', '42', '-3.14']];
        $options = new EncodeOptions(delimiter: '|');
        $expected = 'items[3|]: "true"|"42"|"-3.14"';
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_comma_in_object_value_with_tab_delimiter(): void
    {
        // P1 High Priority: Commas in object values should not be quoted with tab delimiter
        // Tests document-level delimiter context
        $input = ['note' => 'a,b', 'items' => ['x', 'y']];
        $options = new EncodeOptions(delimiter: "\t");
        $expected = "note: a,b\nitems[2\t]: x\ty";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    // Phase 1.2: Delimiter Quoting Completeness

    public function test_encode_value_with_multiple_delimiters_quoted(): void
    {
        // Values with multiple occurrences of the active delimiter must be quoted
        $input = ['tags' => ['a,b,c', 'x,y,z']];
        $expected = 'tags[2]: "a,b,c","x,y,z"';
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_value_starting_with_delimiter_quoted(): void
    {
        // Values starting with the active delimiter must be quoted
        $input = ['items' => [',start', 'normal']];
        $expected = 'items[2]: ",start",normal';
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_value_ending_with_delimiter_quoted(): void
    {
        // Values ending with the active delimiter must be quoted
        $input = ['items' => ['normal', 'end,']];
        $expected = 'items[2]: normal,"end,"';
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_delimiter_in_nested_array_context(): void
    {
        // Nested arrays can have different delimiters; inner context uses its own delimiter
        $input = [
            'data' => [
                ['items' => ['a', 'b']],
                ['items' => ['c,d', 'e']],  // Comma in value should be quoted
            ],
        ];
        $expected = "data[2]:\n  - items[2]: a,b\n  - items[2]: \"c,d\",e";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_delimiter_context_switching_in_deep_nesting(): void
    {
        // Test delimiter inheritance: document delimiter applies to all arrays without explicit delimiter
        $input = [
            'outer' => [
                ['inner' => ['a,b', 'c']],  // Nested inline array inherits document delimiter (tab)
                ['inner' => ['d', 'e']],
            ],
        ];
        $options = new EncodeOptions(delimiter: "\t");
        // Document delimiter is tab, so inner arrays also use tab
        // The comma in "a,b" doesn't need quoting because tab is the active delimiter
        $expected = "outer[2]:\n  - inner[2\t]: a,b\tc\n  - inner[2\t]: d\te";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }
}
