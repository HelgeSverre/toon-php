<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\EncodeOptions;
use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function test_toon_helper_encodes_value(): void
    {
        $result = toon(['name' => 'Alice', 'age' => 30]);
        $this->assertStringContainsString('name: Alice', $result);
        $this->assertStringContainsString('age: 30', $result);
    }

    public function test_toon_helper_accepts_options(): void
    {
        $options = new EncodeOptions(indent: 4);
        $result = toon(['parent' => ['child' => 'value']], $options);
        // Should have 4 spaces for indentation
        $this->assertStringContainsString('    child: value', $result);
    }

    public function test_toon_compact_uses_minimal_indentation(): void
    {
        $result = toon_compact(['parent' => ['child' => 'value']]);
        // Compact mode uses indent: 0 (no indentation)
        $this->assertStringContainsString('child: value', $result);
        $this->assertStringNotContainsString('  child', $result);
    }

    public function test_toon_readable_uses_generous_indentation(): void
    {
        $result = toon_readable(['parent' => ['child' => 'value']]);
        // Should have 4 spaces for indentation (readable mode)
        $this->assertStringContainsString('    child: value', $result);
    }

    public function test_toon_tabular_uses_tab_delimiter(): void
    {
        $result = toon_tabular(['items' => ['a', 'b', 'c']]);
        // Should contain tab delimiter marker
        $this->assertStringContainsString("\t", $result);
    }

    public function test_toon_compare_returns_statistics(): void
    {
        $stats = toon_compare(['name' => 'Alice', 'age' => 30]);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('toon', $stats);
        $this->assertArrayHasKey('json', $stats);
        $this->assertArrayHasKey('savings', $stats);
        $this->assertArrayHasKey('savings_percent', $stats);
        $this->assertIsInt($stats['toon']);
        $this->assertIsInt($stats['json']);
        $this->assertTrue(is_int($stats['savings']) || is_float($stats['savings']));
        $this->assertIsString($stats['savings_percent']);
        $this->assertStringContainsString('%', $stats['savings_percent']);
    }

    public function test_toon_size_returns_character_count(): void
    {
        $size = toon_size(['name' => 'Alice']);
        $this->assertIsInt($size);
        $this->assertGreaterThan(0, $size);
    }

    public function test_toon_estimate_tokens_returns_estimate(): void
    {
        $tokens = toon_estimate_tokens(['name' => 'Alice', 'age' => 30]);
        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);
    }

    public function test_toon_decode_decodes_primitives(): void
    {
        $this->assertEquals(42, toon_decode('42'));
        $this->assertEquals('hello', toon_decode('hello'));
        $this->assertEquals(true, toon_decode('true'));
        $this->assertEquals(false, toon_decode('false'));
        $this->assertEquals(null, toon_decode('null'));
    }

    public function test_toon_decode_decodes_arrays(): void
    {
        $result = toon_decode('[3]: a,b,c');
        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    public function test_toon_decode_decodes_objects(): void
    {
        $toon = <<<'TOON'
id: 123
name: Ada
active: true
TOON;

        $result = toon_decode($toon);
        $this->assertEquals(['id' => 123, 'name' => 'Ada', 'active' => true], $result);
    }

    public function test_toon_decode_decodes_nested_structures(): void
    {
        $toon = <<<'TOON'
user:
  id: 123
  email: ada@example.com
  metadata:
    active: true
    score: 9.5
TOON;

        $result = toon_decode($toon);
        $expected = [
            'user' => [
                'id' => 123,
                'email' => 'ada@example.com',
                'metadata' => [
                    'active' => true,
                    'score' => 9.5,
                ],
            ],
        ];
        $this->assertEquals($expected, $result);
    }

    public function test_toon_decode_accepts_options(): void
    {
        $toon = <<<'TOON'
user:
    id: 123
    name: Ada
TOON;

        // Decode with custom indent option
        $options = new DecodeOptions(indent: 4);
        $result = toon_decode($toon, $options);
        $this->assertEquals(['user' => ['id' => 123, 'name' => 'Ada']], $result);
    }

    public function test_toon_decode_lenient_allows_forgiving_parsing(): void
    {
        $toon = <<<'TOON'
user:
  id: 123
  name: Ada
TOON;

        $result = toon_decode_lenient($toon);
        $this->assertEquals(['user' => ['id' => 123, 'name' => 'Ada']], $result);
    }

    public function test_encode_decode_roundtrip(): void
    {
        $original = [
            'name' => 'Alice',
            'age' => 30,
            'tags' => ['php', 'testing', 'toon'],
            'metadata' => [
                'active' => true,
                'score' => 9.5,
            ],
        ];

        $encoded = toon($original);
        $decoded = toon_decode($encoded);

        $this->assertEquals($original, $decoded);
    }

    public function test_compact_decode_roundtrip(): void
    {
        // Compact mode with indent: 0 works for flat structures
        $original = ['id' => 1, 'name' => 'Bob', 'active' => true];

        $encoded = toon_compact($original);
        $decoded = toon_decode($encoded);

        $this->assertEquals($original, $decoded);
    }

    public function test_readable_decode_roundtrip(): void
    {
        // Readable mode with indent: 4 works for nested structures
        $original = ['user' => ['id' => 1, 'name' => 'Carol']];

        $encoded = toon_readable($original);
        $decoded = toon_decode($encoded, new DecodeOptions(indent: 4));

        $this->assertEquals($original, $decoded);
    }
}
