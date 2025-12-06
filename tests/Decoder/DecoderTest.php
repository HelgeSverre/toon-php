<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests\Decoder;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\Exceptions\CountMismatchException;
use HelgeSverre\Toon\Exceptions\IndentationException;
use HelgeSverre\Toon\Exceptions\SyntaxException;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

final class DecoderTest extends TestCase
{
    // ========================================
    // Primitive Decoding Tests
    // ========================================

    public function test_decode_string_unquoted(): void
    {
        $this->assertEquals('hello', Toon::decode('hello'));
        $this->assertEquals('world123', Toon::decode('world123'));
    }

    public function test_decode_string_quoted(): void
    {
        $this->assertEquals('hello world', Toon::decode('"hello world"'));
        $this->assertEquals('', Toon::decode('""'));
    }

    public function test_decode_integer(): void
    {
        $this->assertSame(42, Toon::decode('42'));
        $this->assertSame(-7, Toon::decode('-7'));
        $this->assertSame(0, Toon::decode('0'));
    }

    public function test_decode_float(): void
    {
        $this->assertSame(3.14, Toon::decode('3.14'));
        $this->assertSame(-2.5, Toon::decode('-2.5'));
        $this->assertSame(0.5, Toon::decode('0.5'));
    }

    public function test_decode_scientific_notation(): void
    {
        $this->assertSame(1000000.0, Toon::decode('1e6'));
        $this->assertSame(0.000001, Toon::decode('1e-6'));
        $this->assertSame(1500000.0, Toon::decode('1.5E6'));
    }

    public function test_decode_boolean(): void
    {
        $this->assertTrue(Toon::decode('true'));
        $this->assertFalse(Toon::decode('false'));
    }

    public function test_decode_null(): void
    {
        $this->assertNull(Toon::decode('null'));
    }

    public function test_decode_leading_zero_strings(): void
    {
        // Per §2.4: leading zeros in integer part make it a string
        $this->assertSame('05', Toon::decode('05'));
        $this->assertSame('0001', Toon::decode('0001'));
        $this->assertSame('0777', Toon::decode('0777'));
        
        // Negative numbers with leading zeros are also strings
        $this->assertSame('-05', Toon::decode('-05'));
        $this->assertSame('-0001', Toon::decode('-0001'));
        $this->assertSame('-0777', Toon::decode('-0777'));
        
        // But 0.5, 0e1, -0.5, -0e1 are valid numbers
        $this->assertSame(0.5, Toon::decode('0.5'));
        $this->assertSame(0.0, Toon::decode('0e1'));
        $this->assertSame(-0.5, Toon::decode('-0.5'));
        $this->assertSame(0.0, Toon::decode('-0e1'));
    }

    public function test_decode_boolean_like_strings(): void
    {
        // Quoted keywords are strings
        $this->assertSame('true', Toon::decode('"true"'));
        $this->assertSame('false', Toon::decode('"false"'));
        $this->assertSame('null', Toon::decode('"null"'));
    }

    public function test_decode_numeric_strings(): void
    {
        // Quoted numbers are strings
        $this->assertSame('42', Toon::decode('"42"'));
        $this->assertSame('3.14', Toon::decode('"3.14"'));
    }

    // ========================================
    // String Unescaping Tests (§7.1.1, §7.4.1)
    // ========================================

    public function test_decode_escaped_backslash(): void
    {
        $this->assertEquals('\\', Toon::decode('"\\\\"'));
        $this->assertEquals('C:\\Users\\path', Toon::decode('"C:\\\\Users\\\\path"'));
    }

    public function test_decode_escaped_quote(): void
    {
        $this->assertEquals('"', Toon::decode('"\\""'));
        $this->assertEquals('say "hello"', Toon::decode('"say \\"hello\\""'));
    }

    public function test_decode_escaped_newline(): void
    {
        $this->assertEquals("\n", Toon::decode('"\\n"'));
        $this->assertEquals("line1\nline2", Toon::decode('"line1\\nline2"'));
    }

    public function test_decode_escaped_carriage_return(): void
    {
        $this->assertEquals("\r", Toon::decode('"\\r"'));
    }

    public function test_decode_escaped_tab(): void
    {
        $this->assertEquals("\t", Toon::decode('"\\t"'));
        $this->assertEquals("col1\tcol2", Toon::decode('"col1\\tcol2"'));
    }

    public function test_decode_invalid_escape_throws(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid escape sequence: \x');
        Toon::decode('"\\x41"');
    }

    public function test_decode_invalid_unicode_escape_throws(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid escape sequence: \u');
        Toon::decode('"\\u0041"');
    }

    public function test_decode_unterminated_string_throws(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unterminated quoted string');
        Toon::decode('"hello');
    }

    // ========================================
    // Object Decoding Tests
    // ========================================

    public function test_decode_simple_object(): void
    {
        $toon = "id: 123\nname: Alice";
        $expected = ['id' => 123, 'name' => 'Alice'];

        $this->assertEquals($expected, Toon::decode($toon));
    }

    public function test_decode_object_with_string_values(): void
    {
        $toon = "title: Hello World\nauthor: Bob";
        $expected = ['title' => 'Hello World', 'author' => 'Bob'];

        $this->assertEquals($expected, Toon::decode($toon));
    }

    public function test_decode_object_mixed_types(): void
    {
        $toon = "id: 1\nactive: true\ncount: 42\nprice: 9.99\nstatus: null";
        $expected = [
            'id' => 1,
            'active' => true,
            'count' => 42,
            'price' => 9.99,
            'status' => null,
        ];

        $this->assertEquals($expected, Toon::decode($toon));
    }

    // ========================================
    // Indentation Tests
    // ========================================

    public function test_decode_with_indentation(): void
    {
        $toon = "user:\n  id: 123\n  name: Alice";
        $expected = [
            'user' => [
                'id' => 123,
                'name' => 'Alice',
            ],
        ];

        $this->assertEquals($expected, Toon::decode($toon));
    }

    public function test_decode_deeply_nested_object(): void
    {
        $toon = "level1:\n  level2:\n    level3: value";
        $expected = [
            'level1' => [
                'level2' => [
                    'level3' => 'value',
                ],
            ],
        ];

        $this->assertEquals($expected, Toon::decode($toon));
    }

    public function test_decode_with_custom_indent(): void
    {
        $toon = "outer:\n    inner: value";
        $options = new DecodeOptions(indent: 4);
        $expected = ['outer' => ['inner' => 'value']];

        $this->assertEquals($expected, Toon::decode($toon, $options));
    }

    public function test_decode_tabs_in_indentation_strict_mode_throws(): void
    {
        $this->expectException(IndentationException::class);
        $this->expectExceptionMessage('Tabs not allowed');

        Toon::decode("\tid: 1");
    }

    public function test_decode_incorrect_indentation_multiple_strict_mode_throws(): void
    {
        $toon = "key:\n   value: test"; // 3 spaces instead of 2
        $this->expectException(IndentationException::class);
        $this->expectExceptionMessage('must be multiple of 2');

        Toon::decode($toon);
    }

    // ========================================
    // Error Handling Tests
    // ========================================

    public function test_decode_missing_colon_in_object_throws(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Missing colon');

        Toon::decode("id: 1\nname Alice"); // Missing colon in object context
    }

    public function test_decode_empty_input_lenient_mode(): void
    {
        $options = DecodeOptions::lenient();
        $this->assertNull(Toon::decode('', $options));
    }

    // ========================================
    // Whitespace Tolerance Tests (§12.11)
    // ========================================

    public function test_decode_with_trailing_newline(): void
    {
        // Per §12.15: decoders SHOULD accept trailing newline
        $toon = "id: 123\n";
        $expected = ['id' => 123];

        $this->assertEquals($expected, Toon::decode($toon));
    }

    public function test_decode_with_whitespace_around_values(): void
    {
        // Whitespace tolerance around values
        $toon = "id:  123  \nname:  Alice  ";
        $expected = ['id' => 123, 'name' => 'Alice'];

        $this->assertEquals($expected, Toon::decode($toon));
    }

    // ========================================
    // Inline Array Tests (§8.1)
    // ========================================

    public function test_decode_inline_array_simple(): void
    {
        $toon = '[3]: a,b,c';
        $expected = ['a', 'b', 'c'];

        $this->assertEquals($expected, Toon::decode($toon));
    }

    public function test_decode_inline_array_with_numbers(): void
    {
        $toon = '[4]: 1,2,3,4';
        $expected = [1, 2, 3, 4];

        $this->assertEquals($expected, Toon::decode($toon));
    }

    public function test_decode_inline_array_with_mixed_types(): void
    {
        $toon = '[5]: 42,true,false,null,hello';
        $expected = [42, true, false, null, 'hello'];

        $this->assertEquals($expected, Toon::decode($toon));
    }

    public function test_decode_inline_array_with_quoted_strings(): void
    {
        $toon = '[3]: "hello world","foo,bar",test';
        $expected = ['hello world', 'foo,bar', 'test'];

        $this->assertEquals($expected, Toon::decode($toon));
    }

    public function test_decode_inline_array_count_mismatch_throws(): void
    {
        $this->expectException(CountMismatchException::class);
        $this->expectExceptionMessage('expected 3, got 2');

        Toon::decode('[3]: a,b');
    }

    public function test_decode_inline_array_in_object(): void
    {
        $toon = "nums: [3]: 1,2,3\nname: test";
        $expected = [
            'nums' => [1, 2, 3],
            'name' => 'test',
        ];

        $this->assertEquals($expected, Toon::decode($toon));
    }

    public function test_decode_inline_array_with_floats(): void
    {
        $toon = '[3]: 1.5,2.7,3.9';
        $expected = [1.5, 2.7, 3.9];

        $this->assertEquals($expected, Toon::decode($toon));
    }

    public function test_decode_inline_array_with_empty_strings(): void
    {
        $toon = '[3]: a,"",c';
        $expected = ['a', '', 'c'];

        $this->assertEquals($expected, Toon::decode($toon));
    }

    // ========================================
    // List Array Tests (§8.3)
    // ========================================

    public function test_decode_list_array_simple(): void
    {
        $toon = "[3]:\n  - apple\n  - banana\n  - cherry";
        $expected = ['apple', 'banana', 'cherry'];

        $this->assertEquals($expected, Toon::decode($toon));
    }

    public function test_decode_list_array_with_numbers(): void
    {
        $toon = "[3]:\n  - 1\n  - 2\n  - 3";
        $expected = [1, 2, 3];

        $this->assertEquals($expected, Toon::decode($toon));
    }

    public function test_decode_list_array_with_nested_objects(): void
    {
        $toon = "[2]:\n  - id: 1\n    name: Alice\n  - id: 2\n    name: Bob";
        $expected = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];

        $this->assertEquals($expected, Toon::decode($toon));
    }

    public function test_decode_list_array_count_mismatch_throws(): void
    {
        $this->expectException(CountMismatchException::class);
        $this->expectExceptionMessage('expected 3, got 2');

        Toon::decode("[3]:\n  - a\n  - b");
    }

    public function test_decode_list_array_missing_hyphen_throws(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('must start with hyphen');

        Toon::decode("[2]:\n  - a\n  b");
    }

    public function test_decode_list_array_in_object(): void
    {
        $toon = "items:\n  [2]:\n    - apple\n    - banana\ncount: 2";
        $expected = [
            'items' => ['apple', 'banana'],
            'count' => 2,
        ];

        $this->assertEquals($expected, Toon::decode($toon));
    }

    public function test_decode_list_array_with_null_values(): void
    {
        $toon = "[3]:\n  - a\n  - null\n  - c";
        $expected = ['a', null, 'c'];

        $this->assertEquals($expected, Toon::decode($toon));
    }

    // ========================================
    // Tabular Array Tests (§9)
    // ========================================

    public function test_decode_tabular_array_simple(): void
    {
        $toon = "[2]{id,name}:\n  1,Alice\n  2,Bob";
        $expected = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];

        $this->assertEquals($expected, Toon::decode($toon));
    }

    public function test_decode_tabular_array_without_length(): void
    {
        $toon = "{id,name}:\n  1,Alice\n  2,Bob\n  3,Charlie";
        $expected = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Charlie'],
        ];

        $this->assertEquals($expected, Toon::decode($toon));
    }

    public function test_decode_tabular_array_with_mixed_types(): void
    {
        $toon = "[2]{id,name,active}:\n  1,Alice,true\n  2,Bob,false";
        $expected = [
            ['id' => 1, 'name' => 'Alice', 'active' => true],
            ['id' => 2, 'name' => 'Bob', 'active' => false],
        ];

        $this->assertEquals($expected, Toon::decode($toon));
    }

    public function test_decode_tabular_array_width_mismatch_throws(): void
    {
        $this->expectException(CountMismatchException::class);
        $this->expectExceptionMessage('expected 3 values, got 2');

        Toon::decode("[1]{id,name,email}:\n  1,Alice");
    }

    public function test_decode_tabular_array_count_mismatch_throws(): void
    {
        $this->expectException(CountMismatchException::class);
        $this->expectExceptionMessage('expected 3 rows, got 2');

        Toon::decode("[3]{id,name}:\n  1,Alice\n  2,Bob");
    }

    public function test_decode_tabular_array_in_object(): void
    {
        $toon = "users:\n  [2]{id,name}:\n    1,Alice\n    2,Bob\ntotal: 2";
        $expected = [
            'users' => [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ],
            'total' => 2,
        ];

        $this->assertEquals($expected, Toon::decode($toon));
    }

    public function test_decode_tabular_array_with_quoted_fields(): void
    {
        $toon = "[2]{\"first name\",\"last name\"}:\n  Alice,Smith\n  Bob,Jones";
        $expected = [
            ['first name' => 'Alice', 'last name' => 'Smith'],
            ['first name' => 'Bob', 'last name' => 'Jones'],
        ];

        $this->assertEquals($expected, Toon::decode($toon));
    }

    public function test_decode_tabular_array_with_null_values(): void
    {
        $toon = "[2]{id,name,email}:\n  1,Alice,null\n  2,Bob,null";
        $expected = [
            ['id' => 1, 'name' => 'Alice', 'email' => null],
            ['id' => 2, 'name' => 'Bob', 'email' => null],
        ];

        $this->assertEquals($expected, Toon::decode($toon));
    }

    public function test_decode_tabular_array_single_row(): void
    {
        $toon = "[1]{id,name}:\n  1,Alice";
        $expected = [
            ['id' => 1, 'name' => 'Alice'],
        ];

        $this->assertEquals($expected, Toon::decode($toon));
    }
}
