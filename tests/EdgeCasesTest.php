<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

final class EdgeCasesTest extends TestCase
{
    public function test_encode_very_long_string(): void
    {
        $longString = str_repeat('a', 1000);
        $encoded = Toon::encode($longString);
        $this->assertEquals($longString, $encoded);
    }

    public function test_encode_very_long_string_with_special_chars(): void
    {
        $longString = str_repeat('a:b,c', 200);
        $encoded = Toon::encode($longString);
        $this->assertStringStartsWith('"', $encoded);
        $this->assertStringEndsWith('"', $encoded);
    }

    public function test_encode_array_with_many_items(): void
    {
        $items = range(1, 100);
        $encoded = Toon::encode($items);
        $this->assertStringStartsWith('[100]:', $encoded);
        $this->assertStringContainsString('100', $encoded);
    }

    public function test_encode_object_with_many_keys(): void
    {
        $object = [];
        for ($i = 0; $i < 50; $i++) {
            $object["key{$i}"] = $i;
        }
        $encoded = Toon::encode($object);
        $this->assertStringContainsString('key0: 0', $encoded);
        $this->assertStringContainsString('key49: 49', $encoded);
    }

    public function test_encode_deeply_nested_objects(): void
    {
        $deep = ['level1' => ['level2' => ['level3' => ['level4' => ['level5' => 'deep']]]]];
        $expected = "level1:\n  level2:\n    level3:\n      level4:\n        level5: deep";
        $this->assertEquals($expected, Toon::encode($deep));
    }

    public function test_encode_very_deeply_nested_objects(): void
    {
        $deep = [
            'a' => [
                'b' => [
                    'c' => [
                        'd' => [
                            'e' => [
                                'f' => [
                                    'g' => [
                                        'h' => [
                                            'i' => ['j' => 'end'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $encoded = Toon::encode($deep);
        $this->assertStringContainsString('j: end', $encoded);
        // Check for proper indentation depth
        $lines = explode("\n", $encoded);
        $lastLine = end($lines);
        $this->assertStringStartsWith('                  ', $lastLine); // 18 spaces (9 levels * 2)
    }

    public function test_encode_mixed_unicode_and_ascii(): void
    {
        $input = ['name' => 'Ada', 'greeting' => '你好', 'emoji' => '🚀', 'text' => 'Hello World'];
        $expected = "name: Ada\ngreeting: 你好\nemoji: 🚀\ntext: Hello World";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_all_special_characters_in_key(): void
    {
        $key = "!@#$%^&*()_+-=[]{}|;':\",./<>?";
        $input = [$key => 'value'];
        $encoded = Toon::encode($input);
        $this->assertStringContainsString('value', $encoded);
    }

    public function test_encode_datetime_with_timezone(): void
    {
        $date = new \DateTime('2025-01-01T00:00:00+05:30');
        $encoded = Toon::encode($date);
        $this->assertStringContainsString('2025-01-01', $encoded);
    }

    public function test_encode_datetime_in_object(): void
    {
        $input = ['created' => new \DateTime('2025-01-01T12:00:00Z')];
        $encoded = Toon::encode($input);
        $this->assertStringStartsWith('created:', $encoded);
        $this->assertStringContainsString('2025-01-01', $encoded);
    }

    public function test_encode_null_values_in_various_contexts(): void
    {
        $input = [
            'standalone' => null,
            'inArray' => [null, null, null],
            'inObject' => ['nested' => null],
        ];
        $encoded = Toon::encode($input);
        $this->assertStringContainsString('standalone: null', $encoded);
        $this->assertStringContainsString('inArray[3]: null,null,null', $encoded);
        $this->assertStringContainsString('nested: null', $encoded);
    }

    public function test_encode_zero_values(): void
    {
        $input = ['zero' => 0, 'zeroFloat' => 0.0, 'negZero' => -0.0];
        $expected = "zero: 0\nzeroFloat: 0\nnegZero: 0";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_custom_indent_size(): void
    {
        $input = ['a' => ['b' => ['c' => 'd']]];
        $options = new EncodeOptions(indent: 4);
        $expected = "a:\n    b:\n        c: d";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_encode_custom_indent_size_large(): void
    {
        $input = ['a' => ['b' => 'c']];
        $options = new EncodeOptions(indent: 8);
        $expected = "a:\n        b: c";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_encode_array_with_boolean_and_string_booleans(): void
    {
        $input = [true, 'true', false, 'false'];
        $expected = '[4]: true,"true",false,"false"';
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_numbers_and_string_numbers(): void
    {
        $input = [42, '42', 3.14, '3.14'];
        $expected = '[4]: 42,"42",3.14,"3.14"';
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_key_order_preservation(): void
    {
        // PHP preserves array key order
        $input = ['z' => 1, 'a' => 2, 'm' => 3];
        $expected = "z: 1\na: 2\nm: 3";
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_array_with_all_special_values(): void
    {
        $input = [null, true, false, 0, '', 'null', 'true', 'false', '0'];
        $expected = '[9]: null,true,false,0,"","null","true","false","0"';
        $this->assertEquals($expected, Toon::encode($input));
    }

    public function test_encode_consecutive_empty_strings(): void
    {
        $input = ['', '', ''];
        $expected = '[3]: "","",""';
        $this->assertEquals($expected, Toon::encode($input));
    }

    // Phase 2.4: Indentation tests

    public function test_encode_zero_indentation_consistent_across_levels(): void
    {
        // Zero indentation should produce no spaces at all nesting levels
        $input = ['a' => ['b' => ['c' => 'd']]];
        $options = new EncodeOptions(indent: 0);
        $expected = "a:\nb:\nc: d";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_encode_custom_indentation_applied_to_all_levels(): void
    {
        // Custom indent: 4 should apply 4 spaces per level
        $input = ['level1' => ['level2' => ['level3' => 'value']]];
        $options = new EncodeOptions(indent: 4);
        $expected = "level1:\n    level2:\n        level3: value";
        $this->assertEquals($expected, Toon::encode($input, $options));
    }

    public function test_encode_key_value_spacing_in_list_item_objects(): void
    {
        // List items should have consistent spacing after colons
        // Use non-uniform objects to force list format (not tabular)
        $input = [
            'items' => [
                ['id' => 1, 'active' => true],
                ['id' => 2], // Missing 'active' field forces list format
            ],
        ];
        $encoded = Toon::encode($input);
        $lines = explode("\n", $encoded);
        // Each property line should have format "  - key: value" with single space after colon
        $foundIdLine = false;
        $foundActiveLine = false;
        foreach ($lines as $line) {
            if (str_contains($line, '- id:')) {
                $this->assertStringContainsString('- id: ', $line);
                $this->assertStringNotContainsString('- id:  ', $line); // no double space
                $foundIdLine = true;
            }
            if (str_contains($line, 'active:')) {
                $this->assertStringContainsString('active: ', $line);
                $this->assertStringNotContainsString('active:  ', $line); // no double space
                $foundActiveLine = true;
            }
        }
        $this->assertTrue($foundIdLine, 'Should have found id line with list marker');
        $this->assertTrue($foundActiveLine, 'Should have found active line');
    }
}
