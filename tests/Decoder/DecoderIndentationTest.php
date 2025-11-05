<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests\Decoder;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\Exceptions\IndentationException;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

final class DecoderIndentationTest extends TestCase
{
    // ========================================
    // A. Valid Indentation Tests - 6 tests
    // ========================================

    public function test_decode_depth_0_no_indentation(): void
    {
        $toon = "id: 1\nname: Alice";
        $result = Toon::decode($toon);

        $this->assertEquals(['id' => 1, 'name' => 'Alice'], $result);
    }

    public function test_decode_depth_1_two_spaces(): void
    {
        $toon = "user:\n  id: 1\n  name: Alice";
        $result = Toon::decode($toon);

        $this->assertEquals(['user' => ['id' => 1, 'name' => 'Alice']], $result);
    }

    public function test_decode_depth_2_four_spaces(): void
    {
        $toon = "data:\n  user:\n    id: 1\n    name: Alice";
        $result = Toon::decode($toon);

        $this->assertEquals([
            'data' => [
                'user' => [
                    'id' => 1,
                    'name' => 'Alice',
                ],
            ],
        ], $result);
    }

    public function test_decode_depth_3_six_spaces(): void
    {
        $toon = "root:\n  data:\n    user:\n      id: 1\n      name: Alice";
        $result = Toon::decode($toon);

        $this->assertEquals([
            'root' => [
                'data' => [
                    'user' => [
                        'id' => 1,
                        'name' => 'Alice',
                    ],
                ],
            ],
        ], $result);
    }

    public function test_decode_mixed_depths_all_valid(): void
    {
        $toon = "a: 1\nb:\n  c: 2\n  d:\n    e: 3\nf: 4";
        $result = Toon::decode($toon);

        $this->assertEquals([
            'a' => 1,
            'b' => [
                'c' => 2,
                'd' => ['e' => 3],
            ],
            'f' => 4,
        ], $result);
    }

    public function test_decode_custom_indent_four_spaces(): void
    {
        $options = new DecodeOptions(indent: 4);
        $toon = "user:\n    id: 1\n    name: Alice";

        $result = Toon::decode($toon, $options);

        $this->assertEquals(['user' => ['id' => 1, 'name' => 'Alice']], $result);
    }

    // ========================================
    // B. Invalid Indentation Multiple Tests - 8 tests
    // ========================================

    public function test_strict_indentation_1_space_invalid(): void
    {
        $this->expectException(IndentationException::class);
        $this->expectExceptionMessage('must be multiple of 2 (got 1 spaces)');

        Toon::decode("key:\n value: test");
    }

    public function test_strict_indentation_3_spaces_invalid(): void
    {
        $this->expectException(IndentationException::class);
        $this->expectExceptionMessage('must be multiple of 2 (got 3 spaces)');

        Toon::decode("key:\n   value: test");
    }

    public function test_strict_indentation_5_spaces_invalid(): void
    {
        $this->expectException(IndentationException::class);
        $this->expectExceptionMessage('must be multiple of 2 (got 5 spaces)');

        Toon::decode("key:\n     value: test");
    }

    public function test_strict_indentation_7_spaces_invalid(): void
    {
        $this->expectException(IndentationException::class);
        $this->expectExceptionMessage('must be multiple of 2 (got 7 spaces)');

        Toon::decode("key:\n       value: test");
    }

    public function test_strict_indentation_custom_4_not_multiple(): void
    {
        $this->expectException(IndentationException::class);
        $this->expectExceptionMessage('must be multiple of 4');

        $options = new DecodeOptions(indent: 4);
        Toon::decode("key:\n  value: test", $options);
    }

    public function test_lenient_indentation_1_space_floor_to_depth_0(): void
    {
        $options = DecodeOptions::lenient();

        // 1 space / 2 = floor(0.5) = 0
        $result = Toon::decode("key:\n value: test", $options);

        // Since depth becomes 0, it's treated as sibling, not child
        $this->assertEquals(['key' => null, 'value' => 'test'], $result);
    }

    public function test_lenient_indentation_3_spaces_floor_to_depth_1(): void
    {
        $options = DecodeOptions::lenient();

        // 3 spaces / 2 = floor(1.5) = 1
        $result = Toon::decode("key:\n   value: test", $options);

        $this->assertEquals(['key' => ['value' => 'test']], $result);
    }

    public function test_lenient_indentation_5_spaces_floor_to_depth_2(): void
    {
        $options = DecodeOptions::lenient();

        // 5 spaces / 2 = floor(2.5) = 2
        $result = Toon::decode("a:\n  b:\n     c: test", $options);

        $this->assertEquals(['a' => ['b' => ['c' => 'test']]], $result);
    }

    // ========================================
    // C. Tab Indentation Tests - 4 tests
    // ========================================

    public function test_strict_single_tab_at_start(): void
    {
        $this->expectException(IndentationException::class);
        $this->expectExceptionMessage('Tabs not allowed');

        Toon::decode("\tid: 1");
    }

    public function test_strict_multiple_tabs_at_start(): void
    {
        $this->expectException(IndentationException::class);
        $this->expectExceptionMessage('Tabs not allowed');

        Toon::decode("key:\n\t\tvalue: test");
    }

    public function test_strict_space_then_tab(): void
    {
        $this->expectException(IndentationException::class);
        $this->expectExceptionMessage('Tabs not allowed');

        Toon::decode("key:\n \tvalue: test");
    }

    public function test_lenient_tabs_implementation_defined(): void
    {
        // Current implementation: tabs accepted in lenient mode
        // This is acceptable per spec (implementation-defined)
        $options = DecodeOptions::lenient();

        // Should not throw - tabs are accepted in lenient mode
        $result = Toon::decode("\tid: 1", $options);
        $this->assertEquals(['id' => 1], $result);
    }

    // ========================================
    // D. Edge Case Tests - 4 tests
    // ========================================

    public function test_zero_indent_size_all_lines_depth_0(): void
    {
        $options = new DecodeOptions(indent: 0);

        // With indent size 0, all lines should be depth 0
        $toon = "a: 1\n  b: 2\n    c: 3";
        $result = Toon::decode($toon, $options);

        // All treated as siblings at root level
        $this->assertEquals(['a' => 1, 'b' => 2, 'c' => 3], $result);
    }

    public function test_deeply_nested_valid_indentation(): void
    {
        $toon = "l1:\n  l2:\n    l3:\n      l4:\n        l5:\n          value: deep";

        $result = Toon::decode($toon);

        $this->assertEquals([
            'l1' => [
                'l2' => [
                    'l3' => [
                        'l4' => [
                            'l5' => [
                                'value' => 'deep',
                            ],
                        ],
                    ],
                ],
            ],
        ], $result);
    }

    public function test_indentation_error_shows_correct_line_number(): void
    {
        try {
            Toon::decode("id: 1\nname: Alice\n   invalid: test");
            $this->fail('Expected IndentationException');
        } catch (IndentationException $e) {
            $this->assertEquals(3, $e->getToonLine());
            $this->assertStringContainsString('invalid: test', $e->getSnippet() ?? '');
        }
    }

    public function test_indentation_preserves_value_content(): void
    {
        $toon = "text:\n  value: \"  spaces  in  string  \"";

        $result = Toon::decode($toon);

        // Spaces inside string should be preserved
        $this->assertEquals(['text' => ['value' => '  spaces  in  string  ']], $result);
    }
}
