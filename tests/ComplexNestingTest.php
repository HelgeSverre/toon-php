<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

final class ComplexNestingTest extends TestCase
{
    // Phase 3.1: Complex Nesting Tests

    public function test_encode_tabular_in_object_in_tabular(): void
    {
        // 3-level nesting: tabular → object → tabular
        // Outer layer: tabular array of objects with primitive 'id' and nested object 'meta'
        // Middle layer: object with primitive 'count' and nested tabular array 'tags'
        // Inner layer: tabular array of objects with primitive fields
        $input = [
            'data' => [
                [
                    'id' => 1,
                    'meta' => [
                        'count' => 5,
                        'tags' => [
                            ['name' => 'urgent', 'priority' => 1],
                            ['name' => 'review', 'priority' => 2],
                        ],
                    ],
                ],
                [
                    'id' => 2,
                    'meta' => [
                        'count' => 3,
                        'tags' => [
                            ['name' => 'draft', 'priority' => 3],
                            ['name' => 'pending', 'priority' => 4],
                        ],
                    ],
                ],
            ],
        ];

        // Outer tabular format breaks down to list format due to nested non-primitive
        $expected = "data[2]:\n"
            ."  - id: 1\n"
            ."    meta:\n"
            ."      count: 5\n"
            ."      tags[2]{name,priority}:\n"
            ."        urgent,1\n"
            ."        review,2\n"
            ."  - id: 2\n"
            ."    meta:\n"
            ."      count: 3\n"
            ."      tags[2]{name,priority}:\n"
            ."        draft,3\n"
            .'        pending,4';

        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_array_of_arrays_with_different_lengths(): void
    {
        // Array of arrays with varying lengths - tests list format with different inline array sizes
        $input = [
            'matrix' => [
                [1, 2],
                [3, 4, 5],
                [6],
                [7, 8, 9, 10],
            ],
        ];

        $expected = "matrix[4]:\n"
            ."  - [2]: 1,2\n"
            ."  - [3]: 3,4,5\n"
            ."  - [1]: 6\n"
            .'  - [4]: 7,8,9,10';

        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_array_of_arrays_with_empty_arrays(): void
    {
        // Array of arrays containing empty arrays - tests empty array handling in list format
        $input = [
            'data' => [
                [1, 2],
                [],
                [3],
                [],
                [4, 5, 6],
            ],
        ];

        $expected = "data[5]:\n"
            ."  - [2]: 1,2\n"
            ."  - [0]:\n"
            ."  - [1]: 3\n"
            ."  - [0]:\n"
            .'  - [3]: 4,5,6';

        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_inline_array_with_mixed_primitives(): void
    {
        // Inline array with all primitive types mixed together
        $input = [
            'values' => [
                42,
                'text',
                true,
                null,
                3.14,
                false,
                0,
                '',
                -99,
            ],
        ];

        // All primitives, so inline format is used
        $expected = 'values[9]: 42,text,true,null,3.14,false,0,"",-99';

        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_inline_array_with_null_and_numbers(): void
    {
        // Inline array with null interspersed between numbers - tests null handling in inline format
        $input = [
            'sparse' => [null, 0, null, 42, null, -5, null],
        ];

        $expected = 'sparse[7]: null,0,null,42,null,-5,null';

        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_three_level_delimiter_context_switching(): void
    {
        // Deep nesting with delimiter inheritance testing
        // Document level → object with arrays → nested arrays
        // All arrays should inherit the document-level delimiter
        $input = [
            'level1' => [
                'items' => [
                    ['data' => ['a', 'b', 'c']],
                    ['data' => ['d', 'e']],
                ],
                'tags' => ['x', 'y', 'z'],
            ],
        ];

        // Test with tab delimiter - should propagate to all nested arrays
        $options = new EncodeOptions(delimiter: "\t");

        $expected = "level1:\n"
            ."  items[2]:\n"
            ."    - data[3\t]: a\tb\tc\n"
            ."    - data[2\t]: d\te\n"
            ."  tags[3\t]: x\ty\tz";

        $this->assertEquals($expected, Toon::encode($input, $options));
    }
}
