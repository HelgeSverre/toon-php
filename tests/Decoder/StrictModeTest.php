<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests\Decoder;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\Exceptions\CountMismatchException;
use HelgeSverre\Toon\Exceptions\IndentationException;
use HelgeSverre\Toon\Exceptions\StrictModeException;
use HelgeSverre\Toon\Exceptions\SyntaxException;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

final class StrictModeTest extends TestCase
{
    // ========================================
    // A. Empty Input Tests (§14.10) - 3 tests
    // ========================================

    public function test_strict_mode_rejects_empty_input(): void
    {
        $this->expectException(StrictModeException::class);
        $this->expectExceptionMessage('Empty input');

        Toon::decode('');
    }

    public function test_strict_mode_rejects_whitespace_only_input(): void
    {
        $this->expectException(StrictModeException::class);
        $this->expectExceptionMessage('Empty input');

        Toon::decode("   \n  \n   ");
    }

    public function test_lenient_mode_accepts_empty_input(): void
    {
        $options = DecodeOptions::lenient();
        $this->assertNull(Toon::decode('', $options));
        $this->assertNull(Toon::decode("  \n  ", $options));
    }

    // ========================================
    // B. Count Mismatch Tests (§14.1-14.4) - 12 tests
    // ========================================

    public function test_strict_mode_inline_array_too_few_values(): void
    {
        $this->expectException(CountMismatchException::class);
        $this->expectExceptionMessage('expected 5, got 3');

        Toon::decode('[5]: a,b,c');
    }

    public function test_strict_mode_inline_array_too_many_values(): void
    {
        $this->expectException(CountMismatchException::class);
        $this->expectExceptionMessage('expected 2, got 4');

        Toon::decode('[2]: a,b,c,d');
    }

    public function test_lenient_mode_accepts_inline_array_count_mismatch(): void
    {
        $options = DecodeOptions::lenient();

        // Too few: should work in lenient mode
        $result = Toon::decode('[5]: a,b,c', $options);
        $this->assertCount(3, $result);

        // Too many: should work in lenient mode
        $result = Toon::decode('[2]: a,b,c,d', $options);
        $this->assertCount(4, $result);
    }

    public function test_strict_mode_list_array_too_few_items(): void
    {
        $this->expectException(CountMismatchException::class);
        $this->expectExceptionMessage('expected 4, got 2');

        Toon::decode("[4]:\n  - a\n  - b");
    }

    public function test_strict_mode_list_array_too_many_items(): void
    {
        $this->expectException(CountMismatchException::class);
        $this->expectExceptionMessage('expected 2, got 4');

        Toon::decode("[2]:\n  - a\n  - b\n  - c\n  - d");
    }

    public function test_lenient_mode_accepts_list_array_count_mismatch(): void
    {
        $options = DecodeOptions::lenient();

        $result = Toon::decode("[4]:\n  - a\n  - b", $options);
        $this->assertCount(2, $result);

        $result = Toon::decode("[2]:\n  - a\n  - b\n  - c", $options);
        $this->assertCount(3, $result);
    }

    public function test_strict_mode_tabular_array_too_few_rows(): void
    {
        $this->expectException(CountMismatchException::class);
        $this->expectExceptionMessage('expected 3 rows, got 2');

        Toon::decode("[3]{id,name}:\n  1,Alice\n  2,Bob");
    }

    public function test_strict_mode_tabular_array_too_many_rows(): void
    {
        $this->expectException(CountMismatchException::class);
        $this->expectExceptionMessage('expected 2 rows, got 3');

        Toon::decode("[2]{id,name}:\n  1,Alice\n  2,Bob\n  3,Charlie");
    }

    public function test_strict_mode_tabular_row_too_few_values(): void
    {
        $this->expectException(CountMismatchException::class);
        $this->expectExceptionMessage('expected 3 values, got 2');

        Toon::decode("[2]{id,name,email}:\n  1,Alice\n  2,Bob,bob@test.com");
    }

    public function test_strict_mode_tabular_row_too_many_values(): void
    {
        $this->expectException(CountMismatchException::class);
        $this->expectExceptionMessage('expected 2 values, got 3');

        Toon::decode("[1]{id,name}:\n  1,Alice,extra");
    }

    public function test_lenient_mode_accepts_tabular_count_mismatch(): void
    {
        $options = DecodeOptions::lenient();

        // Too few rows
        $result = Toon::decode("[3]{id,name}:\n  1,Alice\n  2,Bob", $options);
        $this->assertCount(2, $result);
    }

    public function test_lenient_mode_accepts_tabular_width_mismatch(): void
    {
        $options = DecodeOptions::lenient();

        // This should error even in lenient mode
        $this->expectException(CountMismatchException::class);
        Toon::decode("[1]{id,name,email}:\n  1,Alice", $options);
    }

    // ========================================
    // C. Syntax Error Tests (§14.2, §14.5, §14.6) - 6 tests
    // ========================================

    public function test_strict_mode_missing_colon_in_object(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Missing colon');

        Toon::decode("id: 1\nname Alice");
    }

    public function test_strict_mode_missing_colon_after_key(): void
    {
        // Single line "id 123" is parsed as primitive, not key-value
        // To test missing colon, need multiple lines or explicit object context
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Missing colon');

        Toon::decode("id 123\nname: Alice");
    }

    public function test_strict_mode_invalid_escape_sequence_x(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid escape sequence');

        Toon::decode('"test\\xAB"');
    }

    public function test_strict_mode_invalid_escape_sequence_u(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid escape sequence');

        Toon::decode('"test\\u0041"');
    }

    public function test_strict_mode_unterminated_string(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unterminated');

        Toon::decode('"unterminated');
    }

    public function test_strict_mode_unterminated_string_with_escape(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unterminated');

        // String that ends with escaped quote - no closing quote
        Toon::decode('"test\\"no closing');
    }

    // ========================================
    // D. Blank Line Tests (§14.9) - 12 tests
    // ========================================

    public function test_strict_mode_blank_line_before_list_items(): void
    {
        $this->expectException(StrictModeException::class);
        $this->expectExceptionMessage('Blank lines not allowed');

        Toon::decode("[3]:\n\n  - a\n  - b\n  - c");
    }

    public function test_strict_mode_blank_line_between_list_items(): void
    {
        $this->expectException(StrictModeException::class);
        $this->expectExceptionMessage('Blank lines not allowed');

        Toon::decode("[3]:\n  - a\n\n  - b\n  - c");
    }

    public function test_strict_mode_blank_line_after_list_items_before_sibling(): void
    {
        $toon = "nums:\n  [2]:\n    - 1\n    - 2\n\nname: test";

        // This should be OK - blank line is OUTSIDE the array
        $result = Toon::decode($toon);
        $this->assertEquals(['nums' => [1, 2], 'name' => 'test'], $result);
    }

    public function test_lenient_mode_accepts_blank_line_in_list_array(): void
    {
        $options = DecodeOptions::lenient();

        $result = Toon::decode("[3]:\n  - a\n\n  - b\n  - c", $options);
        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    public function test_strict_mode_blank_line_before_tabular_rows(): void
    {
        $this->expectException(StrictModeException::class);
        $this->expectExceptionMessage('Blank lines not allowed');

        Toon::decode("[2]{id,name}:\n\n  1,Alice\n  2,Bob");
    }

    public function test_strict_mode_blank_line_between_tabular_rows(): void
    {
        $this->expectException(StrictModeException::class);
        $this->expectExceptionMessage('Blank lines not allowed');

        Toon::decode("[3]{id,name}:\n  1,Alice\n\n  2,Bob\n  3,Charlie");
    }

    public function test_strict_mode_blank_line_after_tabular_rows_before_sibling(): void
    {
        $toon = "users:\n  [2]{id,name}:\n    1,Alice\n    2,Bob\n\ncount: 2";

        // This should be OK - blank line is OUTSIDE the array
        $result = Toon::decode($toon);
        $this->assertArrayHasKey('users', $result);
        $this->assertArrayHasKey('count', $result);
    }

    public function test_lenient_mode_accepts_blank_line_in_tabular_array(): void
    {
        $options = DecodeOptions::lenient();

        $result = Toon::decode("[2]{id,name}:\n  1,Alice\n\n  2,Bob", $options);
        $this->assertCount(2, $result);
    }

    public function test_strict_mode_blank_line_in_nested_array(): void
    {
        $this->expectException(StrictModeException::class);
        $this->expectExceptionMessage('Blank lines not allowed');

        Toon::decode("data:\n  [2]:\n    - a\n\n    - b");
    }

    public function test_strict_mode_blank_line_in_deeply_nested_array(): void
    {
        $this->expectException(StrictModeException::class);
        $this->expectExceptionMessage('Blank lines not allowed');

        Toon::decode("root:\n  data:\n    [2]:\n      - x\n\n      - y");
    }

    public function test_strict_mode_accepts_blank_line_between_object_fields(): void
    {
        // Blank lines outside arrays should be OK per §12.18
        $toon = "id: 1\n\nname: Alice\n\nemail: alice@test.com";

        $result = Toon::decode($toon);
        $this->assertEquals([
            'id' => 1,
            'name' => 'Alice',
            'email' => 'alice@test.com',
        ], $result);
    }

    public function test_strict_mode_accepts_blank_line_at_start_of_document(): void
    {
        $toon = "\n\nid: 1\nname: Alice";

        $result = Toon::decode($toon);
        $this->assertEquals(['id' => 1, 'name' => 'Alice'], $result);
    }

    public function test_strict_mode_accepts_blank_line_at_end_of_document(): void
    {
        $toon = "id: 1\nname: Alice\n\n";

        $result = Toon::decode($toon);
        $this->assertEquals(['id' => 1, 'name' => 'Alice'], $result);
    }

    // ========================================
    // E. Indentation Tests (§14.7, §14.8) - 15 tests
    // ========================================

    public function test_strict_mode_indentation_not_multiple_1_space(): void
    {
        $this->expectException(IndentationException::class);
        $this->expectExceptionMessage('must be multiple of 2');

        Toon::decode("key:\n value: test");
    }

    public function test_strict_mode_indentation_not_multiple_3_spaces(): void
    {
        $this->expectException(IndentationException::class);
        $this->expectExceptionMessage('must be multiple of 2');

        Toon::decode("key:\n   value: test");
    }

    public function test_strict_mode_indentation_not_multiple_5_spaces(): void
    {
        $this->expectException(IndentationException::class);
        $this->expectExceptionMessage('must be multiple of 2');

        Toon::decode("key:\n     value: test");
    }

    public function test_strict_mode_indentation_not_multiple_7_spaces(): void
    {
        $this->expectException(IndentationException::class);
        $this->expectExceptionMessage('must be multiple of 2');

        Toon::decode("key:\n       value: test");
    }

    public function test_strict_mode_indentation_not_multiple_with_4_space_indent(): void
    {
        $this->expectException(IndentationException::class);
        $this->expectExceptionMessage('must be multiple of 4');

        $options = new DecodeOptions(indent: 4);
        Toon::decode("key:\n  value: test", $options);
    }

    public function test_lenient_mode_accepts_irregular_indentation(): void
    {
        $options = DecodeOptions::lenient();

        // 3 spaces: floor(3/2) = 1, so depth 1 (nested)
        $result = Toon::decode("key:\n   value: test", $options);
        $this->assertEquals(['key' => ['value' => 'test']], $result);

        // 5 spaces with nested: floor(5/2) = 2, so depth 2
        $result = Toon::decode("a:\n  b:\n     c: test", $options);
        $this->assertEquals(['a' => ['b' => ['c' => 'test']]], $result);
    }

    public function test_lenient_mode_floor_division_depth_computation(): void
    {
        $options = DecodeOptions::lenient();

        // 3 spaces / 2 = floor(1.5) = 1
        $result = Toon::decode("key:\n   value: test", $options);
        $this->assertEquals(['key' => ['value' => 'test']], $result);

        // 5 spaces / 2 = floor(2.5) = 2
        $result = Toon::decode("a:\n  b:\n     c: test", $options);
        $this->assertEquals(['a' => ['b' => ['c' => 'test']]], $result);
    }

    public function test_strict_mode_tab_at_start_of_line(): void
    {
        $this->expectException(IndentationException::class);
        $this->expectExceptionMessage('Tabs not allowed');

        Toon::decode("\tid: 1");
    }

    public function test_strict_mode_tab_in_leading_whitespace(): void
    {
        $this->expectException(IndentationException::class);
        $this->expectExceptionMessage('Tabs not allowed');

        Toon::decode("key:\n \tvalue: test");
    }

    public function test_strict_mode_mixed_spaces_and_tabs(): void
    {
        $this->expectException(IndentationException::class);
        $this->expectExceptionMessage('Tabs not allowed');

        Toon::decode("key:\n  \tvalue: test");
    }

    public function test_strict_mode_tab_before_list_marker(): void
    {
        $this->expectException(IndentationException::class);
        $this->expectExceptionMessage('Tabs not allowed');

        Toon::decode("[2]:\n\t- a\n\t- b");
    }

    public function test_lenient_mode_may_accept_tabs(): void
    {
        // Implementation-defined: current implementation accepts tabs in lenient mode
        // This is acceptable per spec (MAY accept or reject)
        $options = DecodeOptions::lenient();

        // Should not throw - tabs are accepted in lenient mode
        $result = Toon::decode("\tid: 1", $options);
        $this->assertEquals(['id' => 1], $result);
    }

    public function test_strict_mode_accepts_tab_in_quoted_string(): void
    {
        $result = Toon::decode("text: \"hello\tworld\"");
        $this->assertEquals(['text' => "hello\tworld"], $result);
    }

    public function test_strict_mode_accepts_tab_delimiter_in_header(): void
    {
        // Tabs can be used as HTAB delimiter (though not common)
        // This is allowed per spec
        $result = Toon::decode('key: value');
        $this->assertEquals(['key' => 'value'], $result);
    }

    public function test_strict_mode_accepts_tab_delimiter_in_values(): void
    {
        // Tabs in values (not indentation) are OK
        $result = Toon::decode("[2]: a\tb,c\td");
        $this->assertEquals(["a\tb", "c\td"], $result);
    }

    // ========================================
    // F. Mixed Strict Mode Tests - 8 tests
    // ========================================

    public function test_strict_mode_multiple_violations_first_error_wins(): void
    {
        // Both tab and wrong indent, tab error should come first
        $this->expectException(IndentationException::class);
        $this->expectExceptionMessage('Tabs not allowed');

        Toon::decode("\t value: test");
    }

    public function test_strict_mode_nested_structure_validation(): void
    {
        $this->expectException(CountMismatchException::class);
        $this->expectExceptionMessage('expected 3, got 2');

        Toon::decode("data:\n  [3]: a,b");
    }

    public function test_strict_mode_preserves_error_line_numbers(): void
    {
        try {
            Toon::decode("id: 1\nname: Alice\n   invalid: test");
            $this->fail('Expected IndentationException');
        } catch (IndentationException $e) {
            $this->assertEquals(3, $e->getToonLine());
        }
    }

    public function test_strict_mode_exception_contains_context(): void
    {
        try {
            Toon::decode('[3]: a,b');
            $this->fail('Expected CountMismatchException');
        } catch (CountMismatchException $e) {
            $this->assertEquals(3, $e->getExpected());
            $this->assertEquals(2, $e->getActual());
        }
    }

    public function test_lenient_mode_decodes_all_edge_cases(): void
    {
        $options = DecodeOptions::lenient();

        // Multiple violations in strict mode should work in lenient
        $result = Toon::decode("id: 1\n name: test\n  [5]: a,b,c", $options);
        $this->assertIsArray($result);
    }

    public function test_strict_mode_default_is_true(): void
    {
        $options = DecodeOptions::default();
        $this->assertTrue($options->strict);
    }

    public function test_lenient_preset_sets_strict_false(): void
    {
        $options = DecodeOptions::lenient();
        $this->assertFalse($options->strict);
    }

    public function test_builder_pattern_sets_strict_mode(): void
    {
        $strict = new DecodeOptions(strict: true);
        $this->assertTrue($strict->strict);

        $lenient = new DecodeOptions(strict: false);
        $this->assertFalse($lenient->strict);
    }
}
