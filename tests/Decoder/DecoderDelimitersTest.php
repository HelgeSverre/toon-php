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
    // DelimiterParser::extractFields Tests
    // ========================================

    public function test_extract_fields_simple_field_list(): void
    {
        $result = DelimiterParser::extractFields('{id,name,email}');
        $this->assertEquals(['id', 'name', 'email'], $result);
    }

    public function test_extract_fields_with_whitespace(): void
    {
        $result = DelimiterParser::extractFields('{ id , name , email }');
        $this->assertEquals(['id', 'name', 'email'], $result);
    }

    public function test_extract_fields_single_field(): void
    {
        $result = DelimiterParser::extractFields('{id}');
        $this->assertEquals(['id'], $result);
    }

    public function test_extract_fields_quoted_field_names(): void
    {
        $result = DelimiterParser::extractFields('{"first name","last name"}');
        $this->assertEquals(['first name', 'last name'], $result);
    }

    public function test_extract_fields_mixed_quoted_and_unquoted(): void
    {
        $result = DelimiterParser::extractFields('{id,"full name",email}');
        $this->assertEquals(['id', 'full name', 'email'], $result);
    }

    public function test_extract_fields_throws_on_missing_opening_brace(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid tabular array header');
        DelimiterParser::extractFields('id,name}', 1);
    }

    public function test_extract_fields_throws_on_missing_closing_brace(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid tabular array header');
        DelimiterParser::extractFields('{id,name', 1);
    }

    public function test_extract_fields_throws_on_empty_header(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Empty tabular array header');
        DelimiterParser::extractFields('{}', 1);
    }

    public function test_extract_fields_throws_on_empty_field_name(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Empty field name');
        DelimiterParser::extractFields('{id,,name}', 1);
    }

    public function test_extract_fields_throws_on_whitespace_only_field(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Empty field name');
        DelimiterParser::extractFields('{id,  ,name}', 1);
    }

    // ========================================
    // DelimiterParser::extractLength Tests
    // ========================================

    public function test_extract_length_simple(): void
    {
        $result = DelimiterParser::extractLength('[5]');
        $this->assertSame(5, $result);
    }

    public function test_extract_length_with_whitespace(): void
    {
        $result = DelimiterParser::extractLength('[ 42 ]');
        $this->assertSame(42, $result);
    }

    public function test_extract_length_throws_on_missing_opening_bracket(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid array header');
        DelimiterParser::extractLength('5]', 1);
    }

    public function test_extract_length_throws_on_missing_closing_bracket(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid array header');
        DelimiterParser::extractLength('[5', 1);
    }

    public function test_extract_length_throws_on_empty_brackets(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Empty array header');
        DelimiterParser::extractLength('[]', 1);
    }

    public function test_extract_length_throws_on_zero_length(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid array length');
        DelimiterParser::extractLength('[0]', 1);
    }

    public function test_extract_length_throws_on_non_numeric(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid array length');
        DelimiterParser::extractLength('[abc]', 1);
    }

    public function test_extract_length_throws_on_negative_length(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid array length');
        DelimiterParser::extractLength('[-5]', 1);
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

    // ========================================
    // DelimiterParser::detectArrayFormat Tests
    // ========================================

    public function test_detect_array_format_inline(): void
    {
        $result = DelimiterParser::detectArrayFormat('[3]: a,b,c');
        $this->assertEquals('inline', $result);
    }

    public function test_detect_array_format_list_with_colon(): void
    {
        $result = DelimiterParser::detectArrayFormat('[3]:');
        $this->assertEquals('list', $result);
    }

    public function test_detect_array_format_list_without_colon(): void
    {
        $result = DelimiterParser::detectArrayFormat('[3]');
        $this->assertEquals('list', $result);
    }

    public function test_detect_array_format_tabular_with_length(): void
    {
        $result = DelimiterParser::detectArrayFormat('[2]{id,name}:');
        $this->assertEquals('tabular', $result);
    }

    public function test_detect_array_format_tabular_continuation(): void
    {
        $result = DelimiterParser::detectArrayFormat('{id,name}:');
        $this->assertEquals('tabular', $result);
    }

    public function test_detect_array_format_non_array_line(): void
    {
        $result = DelimiterParser::detectArrayFormat('key: value');
        $this->assertNull($result);
    }
}
