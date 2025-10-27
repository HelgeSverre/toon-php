<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

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
}
