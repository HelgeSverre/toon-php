<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use HelgeSverre\Toon\Primitives;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

final class PrimitivesTest extends TestCase
{
    public function test_encode_safe_strings(): void
    {
        $this->assertEquals('hello', Toon::encode('hello'));
        $this->assertEquals('Ada_99', Toon::encode('Ada_99'));
    }

    public function test_encode_empty_string(): void
    {
        $this->assertEquals('""', Toon::encode(''));
    }

    public function test_encode_boolean_like_strings(): void
    {
        $this->assertEquals('"true"', Toon::encode('true'));
        $this->assertEquals('"false"', Toon::encode('false'));
        $this->assertEquals('"null"', Toon::encode('null'));
    }

    public function test_encode_numeric_strings(): void
    {
        $this->assertEquals('"42"', Toon::encode('42'));
        $this->assertEquals('"-3.14"', Toon::encode('-3.14'));
        $this->assertEquals('"1e-6"', Toon::encode('1e-6'));
        $this->assertEquals('"05"', Toon::encode('05'));
    }

    public function test_encode_strings_with_control_characters(): void
    {
        $this->assertEquals('"line1\\nline2"', Toon::encode("line1\nline2"));
        $this->assertEquals('"tab\\there"', Toon::encode("tab\there"));
        $this->assertEquals('"return\\rcarriage"', Toon::encode("return\rcarriage"));
    }

    public function test_encode_strings_with_backslashes(): void
    {
        $this->assertEquals('"C:\\\\Users\\\\path"', Toon::encode('C:\\Users\\path'));
    }

    public function test_encode_strings_with_structural_characters(): void
    {
        $this->assertEquals('"[3]: x,y"', Toon::encode('[3]: x,y'));
        $this->assertEquals('"- item"', Toon::encode('- item'));
        $this->assertEquals('"[test]"', Toon::encode('[test]'));
        $this->assertEquals('"{key}"', Toon::encode('{key}'));
    }

    public function test_encode_unicode_strings(): void
    {
        $this->assertEquals('café', Toon::encode('café'));
        $this->assertEquals('你好', Toon::encode('你好'));
    }

    public function test_encode_emoji(): void
    {
        $this->assertEquals('🚀', Toon::encode('🚀'));
        $this->assertEquals('hello 👋 world', Toon::encode('hello 👋 world'));
    }

    public function test_encode_integers(): void
    {
        $this->assertEquals('42', Toon::encode(42));
        $this->assertEquals('-7', Toon::encode(-7));
        $this->assertEquals('0', Toon::encode(0));
    }

    public function test_encode_floats(): void
    {
        $this->assertEquals('3.14', Toon::encode(3.14));
    }

    public function test_encode_negative_zero(): void
    {
        $this->assertEquals('0', Toon::encode(-0.0));
    }

    public function test_encode_scientific_notation(): void
    {
        $this->assertEquals('1000000', Toon::encode(1e6));
        $this->assertEquals('0.000001', Toon::encode(1e-6));
    }

    public function test_encode_very_large_numbers(): void
    {
        $this->assertEquals('100000000000000000000', Toon::encode(1e20));
        // PHP_INT_MAX equivalent to Number.MAX_SAFE_INTEGER
        $this->assertEquals('9007199254740991', Toon::encode(9007199254740991));
    }

    public function test_encode_booleans(): void
    {
        $this->assertEquals('true', Toon::encode(true));
        $this->assertEquals('false', Toon::encode(false));
    }

    public function test_encode_null(): void
    {
        $this->assertEquals('null', Toon::encode(null));
    }

    public function test_encode_infinity(): void
    {
        $this->assertEquals('null', Toon::encode(INF));
        $this->assertEquals('null', Toon::encode(-INF));
    }

    public function test_encode_nan(): void
    {
        $this->assertEquals('null', Toon::encode(NAN));
    }

    public function test_encode_padded_strings(): void
    {
        $this->assertEquals('" padded "', Toon::encode(' padded '));
        $this->assertEquals('"  "', Toon::encode('  '));
    }

    public function test_encode_string_with_quotes(): void
    {
        $this->assertEquals('"say \\"hello\\""', Toon::encode('say "hello"'));
    }

    public function test_encode_hex_pattern_strings(): void
    {
        // Hex patterns should be quoted
        $this->assertEquals('"0xFF"', Toon::encode('0xFF'));
        $this->assertEquals('"0x1A2B"', Toon::encode('0x1A2B'));
        $this->assertEquals('"0xDEADBEEF"', Toon::encode('0xDEADBEEF'));
    }

    public function test_encode_binary_pattern_strings(): void
    {
        // Binary patterns should be quoted
        $this->assertEquals('"0b1010"', Toon::encode('0b1010'));
        $this->assertEquals('"0b11111111"', Toon::encode('0b11111111'));
    }

    public function test_encode_case_sensitive_keyword_strings(): void
    {
        // Only lowercase keywords need quoting
        $this->assertEquals('"true"', Toon::encode('true'));
        $this->assertEquals('"false"', Toon::encode('false'));
        $this->assertEquals('"null"', Toon::encode('null'));

        // Mixed/uppercase versions don't need quoting (not keywords)
        $this->assertEquals('True', Toon::encode('True'));
        $this->assertEquals('FALSE', Toon::encode('FALSE'));
        $this->assertEquals('NULL', Toon::encode('NULL'));
        $this->assertEquals('Null', Toon::encode('Null'));
    }

    public function test_is_safe_unquoted_handles_octal_patterns(): void
    {
        $this->assertFalse(Primitives::isSafeUnquoted('0777', ','));
        $this->assertFalse(Primitives::isSafeUnquoted('0123', ','));
        $this->assertFalse(Primitives::isSafeUnquoted('0644', ','));
    }

    public function test_is_safe_unquoted_handles_binary_patterns(): void
    {
        $this->assertFalse(Primitives::isSafeUnquoted('0b1010', ','));
        $this->assertFalse(Primitives::isSafeUnquoted('0b11111111', ','));
    }

    public function test_is_safe_unquoted_handles_hex_patterns(): void
    {
        $this->assertFalse(Primitives::isSafeUnquoted('0xFF', ','));
        $this->assertFalse(Primitives::isSafeUnquoted('0x1a2b', ','));
        $this->assertFalse(Primitives::isSafeUnquoted('0xDEADBEEF', ','));
        // Note: uppercase 0X is not matched by the pattern, so it doesn't require quoting
        // Only lowercase 0x hex patterns are detected
    }

    public function test_is_safe_unquoted_handles_empty_string(): void
    {
        $this->assertFalse(Primitives::isSafeUnquoted('', ','));
    }

    public function test_is_safe_unquoted_handles_whitespace(): void
    {
        $this->assertFalse(Primitives::isSafeUnquoted(' leading', ','));
        $this->assertFalse(Primitives::isSafeUnquoted('trailing ', ','));
        $this->assertFalse(Primitives::isSafeUnquoted(' both ', ','));
    }

    public function test_is_safe_unquoted_handles_keywords(): void
    {
        $this->assertFalse(Primitives::isSafeUnquoted('true', ','));
        $this->assertFalse(Primitives::isSafeUnquoted('false', ','));
        $this->assertFalse(Primitives::isSafeUnquoted('null', ','));
    }

    public function test_is_safe_unquoted_handles_numeric_patterns(): void
    {
        $this->assertFalse(Primitives::isSafeUnquoted('42', ','));
        $this->assertFalse(Primitives::isSafeUnquoted('-3.14', ','));
        $this->assertFalse(Primitives::isSafeUnquoted('1e-6', ','));
        $this->assertFalse(Primitives::isSafeUnquoted('1E10', ','));
    }

    public function test_is_safe_unquoted_handles_structural_chars(): void
    {
        $this->assertFalse(Primitives::isSafeUnquoted('has:colon', ','));
        $this->assertFalse(Primitives::isSafeUnquoted('has[bracket', ','));
        $this->assertFalse(Primitives::isSafeUnquoted('has]bracket', ','));
        $this->assertFalse(Primitives::isSafeUnquoted('has{brace', ','));
        $this->assertFalse(Primitives::isSafeUnquoted('has}brace', ','));
        $this->assertFalse(Primitives::isSafeUnquoted('has"quote', ','));
        $this->assertFalse(Primitives::isSafeUnquoted('has\\backslash', ','));
    }

    public function test_is_safe_unquoted_handles_delimiter(): void
    {
        $this->assertFalse(Primitives::isSafeUnquoted('has,comma', ','));
        $this->assertFalse(Primitives::isSafeUnquoted("has\ttab", "\t"));
        $this->assertFalse(Primitives::isSafeUnquoted('has|pipe', '|'));
    }

    public function test_is_safe_unquoted_handles_list_markers(): void
    {
        $this->assertFalse(Primitives::isSafeUnquoted('-item', ','));
        $this->assertFalse(Primitives::isSafeUnquoted('- item', ','));
    }

    public function test_is_safe_unquoted_allows_safe_strings(): void
    {
        $this->assertTrue(Primitives::isSafeUnquoted('hello', ','));
        $this->assertTrue(Primitives::isSafeUnquoted('Ada_99', ','));
        $this->assertTrue(Primitives::isSafeUnquoted('test123', ','));
        $this->assertTrue(Primitives::isSafeUnquoted('safe_identifier', ','));
    }

    public function test_is_safe_unquoted_key_mode_rejects_spaces(): void
    {
        $this->assertFalse(Primitives::isSafeUnquoted('has spaces', ',', true));
        $this->assertTrue(Primitives::isSafeUnquoted('has spaces', ',', false));
    }

    public function test_encode_key_handles_identifiers(): void
    {
        $this->assertEquals('name', Primitives::encodeKey('name'));
        $this->assertEquals('_private', Primitives::encodeKey('_private'));
        $this->assertEquals('field123', Primitives::encodeKey('field123'));
    }

    public function test_encode_key_handles_dots_in_identifiers(): void
    {
        $this->assertEquals('property.name', Primitives::encodeKey('property.name'));
        $this->assertEquals('nested.deep.field', Primitives::encodeKey('nested.deep.field'));
    }

    public function test_encode_key_quotes_keys_with_special_characters(): void
    {
        $this->assertEquals('"key with spaces"', Primitives::encodeKey('key with spaces'));
        $this->assertEquals('"key:colon"', Primitives::encodeKey('key:colon'));
        $this->assertEquals('"key[bracket"', Primitives::encodeKey('key[bracket'));
        $this->assertEquals('"key,comma"', Primitives::encodeKey('key,comma'));
    }

    public function test_encode_key_quotes_numeric_keys(): void
    {
        $this->assertEquals('"123"', Primitives::encodeKey('123'));
        $this->assertEquals('"0"', Primitives::encodeKey('0'));
    }

    public function test_encode_key_allows_keywords_as_identifiers(): void
    {
        // Keywords are valid identifiers in key position, so they don't need quoting
        $this->assertEquals('true', Primitives::encodeKey('true'));
        $this->assertEquals('false', Primitives::encodeKey('false'));
        $this->assertEquals('null', Primitives::encodeKey('null'));
    }

    public function test_encode_key_quotes_empty_string(): void
    {
        $this->assertEquals('""', Primitives::encodeKey(''));
    }

    public function test_escape_string_handles_backslashes(): void
    {
        $this->assertEquals('\\\\', Primitives::escapeString('\\'));
        $this->assertEquals('\\\\\\\\', Primitives::escapeString('\\\\'));
    }

    public function test_escape_string_handles_quotes(): void
    {
        $this->assertEquals('\\"', Primitives::escapeString('"'));
        $this->assertEquals('say \\"hello\\"', Primitives::escapeString('say "hello"'));
    }

    public function test_escape_string_handles_control_characters(): void
    {
        $this->assertEquals('\\n', Primitives::escapeString("\n"));
        $this->assertEquals('\\r', Primitives::escapeString("\r"));
        $this->assertEquals('\\t', Primitives::escapeString("\t"));
        $this->assertEquals('line1\\nline2', Primitives::escapeString("line1\nline2"));
    }

    public function test_escape_string_handles_combined_escapes(): void
    {
        $this->assertEquals('\\\\n', Primitives::escapeString('\\n'));
        $this->assertEquals('\\\\\\"', Primitives::escapeString('\\"'));
    }

    public function test_encode_primitive_throws_for_unsupported_types(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported primitive type');
        Primitives::encodePrimitive([], ',');
    }

    public function test_encode_primitive_throws_for_objects(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported primitive type');
        Primitives::encodePrimitive(new \stdClass, ',');
    }

    public function test_encode_primitive_handles_zero_integer(): void
    {
        $this->assertEquals('0', Primitives::encodePrimitive(0, ','));
    }

    public function test_encode_primitive_handles_zero_float(): void
    {
        $this->assertEquals('0', Primitives::encodePrimitive(0.0, ','));
    }
}
