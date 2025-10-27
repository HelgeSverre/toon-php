<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

final class ObjectsTest extends TestCase
{
    public function test_encode_simple_object(): void
    {
        $input = ['id' => 123, 'name' => 'Ada', 'active' => true];
        $expected = "id: 123\nname: Ada\nactive: true";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_object_with_null_value(): void
    {
        $input = ['id' => 123, 'value' => null];
        $expected = "id: 123\nvalue: null";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_empty_object(): void
    {
        $this->assertEquals('', Toon::encode([]));
    }

    public function test_encode_object_with_special_chars_in_value(): void
    {
        $this->assertEquals('note: "a:b"', Toon::encode(['note' => 'a:b']));
        $this->assertEquals('note: "a,b"', Toon::encode(['note' => 'a,b']));
    }

    public function test_encode_object_with_control_chars_in_value(): void
    {
        $input = ['text' => "line1\nline2"];
        $expected = 'text: "line1\\nline2"';
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_object_with_quotes_in_value(): void
    {
        $input = ['text' => 'say "hello"'];
        $expected = 'text: "say \\"hello\\""';
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_object_with_padded_values(): void
    {
        $this->assertEquals('text: " padded "', Toon::encode(['text' => ' padded ']));
        $this->assertEquals('text: "  "', Toon::encode(['text' => '  ']));
    }

    public function test_encode_object_with_string_primitives(): void
    {
        $this->assertEquals('v: "true"', Toon::encode(['v' => 'true']));
        $this->assertEquals('v: "42"', Toon::encode(['v' => '42']));
        $this->assertEquals('v: "-7.5"', Toon::encode(['v' => '-7.5']));
    }

    public function test_encode_object_keys_with_special_chars(): void
    {
        $this->assertEquals('"order:id": 7', Toon::encode(['order:id' => 7]));
        $this->assertEquals('"[index]": 5', Toon::encode(['[index]' => 5]));
        $this->assertEquals('"{key}": 5', Toon::encode(['{key}' => 5]));
        $this->assertEquals('"a,b": 1', Toon::encode(['a,b' => 1]));
    }

    public function test_encode_object_keys_with_spaces(): void
    {
        $this->assertEquals('"full name": Ada', Toon::encode(['full name' => 'Ada']));
        $this->assertEquals('" a ": 1', Toon::encode([' a ' => 1]));
    }

    public function test_encode_object_keys_with_leading_hyphen(): void
    {
        $this->assertEquals('"-lead": 1', Toon::encode(['-lead' => 1]));
    }

    public function test_encode_object_with_numeric_key(): void
    {
        $this->assertEquals('"123": x', Toon::encode(['123' => 'x']));
    }

    public function test_encode_object_with_empty_key(): void
    {
        $this->assertEquals('"": 1', Toon::encode(['' => 1]));
    }

    public function test_encode_object_keys_with_control_chars(): void
    {
        $this->assertEquals('"line\\nbreak": 1', Toon::encode(["line\nbreak" => 1]));
        $this->assertEquals('"tab\\there": 2', Toon::encode(["tab\there" => 2]));
    }

    public function test_encode_object_keys_with_quotes(): void
    {
        $input = ['he said "hi"' => 1];
        $expected = '"he said \\"hi\\"": 1';
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_nested_object(): void
    {
        $input = ['a' => ['b' => ['c' => 'deep']]];
        $expected = "a:\n  b:\n    c: deep";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_empty_nested_object(): void
    {
        $input = ['user' => []];
        $expected = 'user:';
        $this->assertEquals($expected, Toon::encode($input));
    }
}
