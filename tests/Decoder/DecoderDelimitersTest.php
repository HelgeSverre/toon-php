<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests\Decoder;

use HelgeSverre\Toon\Decoder\DelimiterParser;
use HelgeSverre\Toon\Exceptions\SyntaxException;
use PHPUnit\Framework\TestCase;

final class DecoderDelimitersTest extends TestCase
{
    // ========================================
    // DelimiterParser::split Tests
    // ========================================

    public function test_split_simple_comma_separated_values(): void
    {
        $result = DelimiterParser::split('a,b,c');
        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    public function test_split_values_with_whitespace_trimming(): void
    {
        $result = DelimiterParser::split('  a  ,  b  ,  c  ');
        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    public function test_split_quoted_strings_containing_delimiters(): void
    {
        $result = DelimiterParser::split('"a,b",c,"d,e"');
        $this->assertEquals(['"a,b"', 'c', '"d,e"'], $result);
    }

    public function test_split_escaped_quotes_in_quoted_strings(): void
    {
        $result = DelimiterParser::split('"a\"b",c');
        $this->assertEquals(['"a\"b"', 'c'], $result);
    }

    public function test_split_empty_values_between_delimiters(): void
    {
        $result = DelimiterParser::split('a,,c');
        $this->assertEquals(['a', '', 'c'], $result);
    }

    public function test_split_leading_delimiter(): void
    {
        $result = DelimiterParser::split(',a,b');
        $this->assertEquals(['', 'a', 'b'], $result);
    }

    public function test_split_trailing_delimiter(): void
    {
        $result = DelimiterParser::split('a,b,');
        $this->assertEquals(['a', 'b', ''], $result);
    }

    public function test_split_multiple_consecutive_delimiters(): void
    {
        $result = DelimiterParser::split('a,,,b');
        $this->assertEquals(['a', '', '', 'b'], $result);
    }

    public function test_split_preserves_whitespace_inside_quoted_strings(): void
    {
        $result = DelimiterParser::split('"  a  ","  b  "');
        $this->assertEquals(['"  a  "', '"  b  "'], $result);
    }

    public function test_split_custom_delimiter(): void
    {
        $result = DelimiterParser::split('a|b|c', '|');
        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    public function test_split_empty_input(): void
    {
        $result = DelimiterParser::split('');
        $this->assertEquals([], $result);
    }

    public function test_split_whitespace_only_input(): void
    {
        $result = DelimiterParser::split('   ');
        $this->assertEquals([], $result);
    }

    public function test_split_escaped_backslash_before_quote(): void
    {
        $result = DelimiterParser::split('"a\\\\",b');
        $this->assertEquals(['"a\\\\"', 'b'], $result);
    }

    public function test_split_complex_nested_escapes(): void
    {
        $result = DelimiterParser::split('"a\\"b\\"c",d');
        $this->assertEquals(['"a\\"b\\"c"', 'd'], $result);
    }

    public function test_split_throws_on_unterminated_quoted_string(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unterminated quoted string');
        DelimiterParser::split('"unterminated', ',', 1);
    }

    // ========================================
    // DelimiterParser::isArrayHeader Tests
    // ========================================

    public function test_is_array_header_inline_array(): void
    {
        $this->assertTrue(DelimiterParser::isArrayHeader('[3]: a,b,c'));
    }

    public function test_is_array_header_list_array(): void
    {
        $this->assertTrue(DelimiterParser::isArrayHeader('[3]'));
    }

    public function test_is_array_header_tabular_array(): void
    {
        $this->assertTrue(DelimiterParser::isArrayHeader('[2]{id,name}'));
    }

    public function test_is_array_header_field_declaration(): void
    {
        $this->assertTrue(DelimiterParser::isArrayHeader('{id,name}'));
    }

    public function test_is_array_header_regular_line(): void
    {
        $this->assertFalse(DelimiterParser::isArrayHeader('key: value'));
    }

    public function test_is_array_header_primitive_value(): void
    {
        $this->assertFalse(DelimiterParser::isArrayHeader('hello'));
    }

    public function test_is_array_header_with_leading_whitespace(): void
    {
        $this->assertTrue(DelimiterParser::isArrayHeader('  [3]:'));
    }
}
