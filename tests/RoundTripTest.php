<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

/**
 * Round-trip tests: encode(x) → decode → encode should produce identical output.
 *
 * These tests verify that the encoder and decoder are compatible across all
 * configuration options (delimiters, length markers, indent sizes).
 */
final class RoundTripTest extends TestCase
{
    // ========================================
    // Section A: Delimiter Round-Trips
    // ========================================

    public function test_inline_array_round_trip_with_comma_delimiter(): void
    {
        $data = ['items' => ['a', 'b', 'c']];
        $options = new EncodeOptions(delimiter: ',');

        $encoded = Toon::encode($data, $options);
        $decoded = Toon::decode($encoded);
        $reencoded = Toon::encode($decoded, $options);

        $this->assertEquals($data, $decoded, 'Decoded data should match original');
        $this->assertSame($encoded, $reencoded, 'Re-encoded output should match original encoding');
    }

    public function test_inline_array_round_trip_with_pipe_delimiter(): void
    {
        $data = ['items' => ['a', 'b', 'c']];
        $options = new EncodeOptions(delimiter: '|');

        $encoded = Toon::encode($data, $options);
        $decoded = Toon::decode($encoded);
        $reencoded = Toon::encode($decoded, $options);

        $this->assertEquals($data, $decoded, 'Decoded data should match original');
        $this->assertSame($encoded, $reencoded, 'Re-encoded output should match original encoding');
    }

    public function test_inline_array_round_trip_with_tab_delimiter(): void
    {
        $data = ['items' => ['a', 'b', 'c']];
        $options = new EncodeOptions(delimiter: "\t");

        $encoded = Toon::encode($data, $options);
        $decoded = Toon::decode($encoded);
        $reencoded = Toon::encode($decoded, $options);

        $this->assertEquals($data, $decoded, 'Decoded data should match original');
        $this->assertSame($encoded, $reencoded, 'Re-encoded output should match original encoding');
    }

    // ========================================
    // Section B: Tabular Arrays
    // ========================================

    public function test_tabular_array_round_trip_with_comma(): void
    {
        $data = [
            'users' => [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ],
        ];
        $options = new EncodeOptions(delimiter: ',');

        $encoded = Toon::encode($data, $options);
        $decoded = Toon::decode($encoded);
        $reencoded = Toon::encode($decoded, $options);

        $this->assertEquals($data, $decoded, 'Decoded data should match original');
        $this->assertSame($encoded, $reencoded, 'Re-encoded output should match original encoding');
    }

    public function test_tabular_array_round_trip_with_pipe(): void
    {
        $data = [
            'users' => [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ],
        ];
        $options = new EncodeOptions(delimiter: '|');

        $encoded = Toon::encode($data, $options);
        $decoded = Toon::decode($encoded);
        $reencoded = Toon::encode($decoded, $options);

        $this->assertEquals($data, $decoded, 'Decoded data should match original');
        $this->assertSame($encoded, $reencoded, 'Re-encoded output should match original encoding');
    }

    public function test_tabular_array_round_trip_with_tab(): void
    {
        $data = [
            'users' => [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ],
        ];
        $options = new EncodeOptions(delimiter: "\t");

        $encoded = Toon::encode($data, $options);
        $decoded = Toon::decode($encoded);
        $reencoded = Toon::encode($decoded, $options);

        $this->assertEquals($data, $decoded, 'Decoded data should match original');
        $this->assertSame($encoded, $reencoded, 'Re-encoded output should match original encoding');
    }

    // ========================================
    // Section C: List Arrays
    // ========================================

    public function test_list_array_round_trip_with_comma(): void
    {
        $data = [
            'items' => [
                ['name' => 'Item 1', 'price' => 10],
                ['name' => 'Item 2', 'price' => 20],
            ],
        ];
        $options = new EncodeOptions(delimiter: ',');

        $encoded = Toon::encode($data, $options);
        $decoded = Toon::decode($encoded);
        $reencoded = Toon::encode($decoded, $options);

        $this->assertEquals($data, $decoded, 'Decoded data should match original');
        $this->assertSame($encoded, $reencoded, 'Re-encoded output should match original encoding');
    }

    public function test_list_array_round_trip_with_pipe(): void
    {
        $data = [
            'items' => [
                ['name' => 'Item 1', 'price' => 10],
                ['name' => 'Item 2', 'price' => 20],
            ],
        ];
        $options = new EncodeOptions(delimiter: '|');

        $encoded = Toon::encode($data, $options);
        $decoded = Toon::decode($encoded);
        $reencoded = Toon::encode($decoded, $options);

        $this->assertEquals($data, $decoded, 'Decoded data should match original');
        $this->assertSame($encoded, $reencoded, 'Re-encoded output should match original encoding');
    }

    // ========================================
    // Section D: Root-Level Arrays
    // ========================================

    public function test_root_inline_array_round_trip_with_pipe(): void
    {
        $data = ['a', 'b', 'c'];
        $options = new EncodeOptions(delimiter: '|');

        $encoded = Toon::encode($data, $options);
        $decoded = Toon::decode($encoded);
        $reencoded = Toon::encode($decoded, $options);

        $this->assertEquals($data, $decoded, 'Decoded data should match original');
        $this->assertSame($encoded, $reencoded, 'Re-encoded output should match original encoding');
    }

    public function test_root_inline_array_round_trip_with_tab(): void
    {
        $data = ['a', 'b', 'c'];
        $options = new EncodeOptions(delimiter: "\t");

        $encoded = Toon::encode($data, $options);
        $decoded = Toon::decode($encoded);
        $reencoded = Toon::encode($decoded, $options);

        $this->assertEquals($data, $decoded, 'Decoded data should match original');
        $this->assertSame($encoded, $reencoded, 'Re-encoded output should match original encoding');
    }

    // ========================================
    // Section E: Complex Nested Structures
    // ========================================

    public function test_complex_nested_structure_round_trip_with_pipe(): void
    {
        $data = [
            'config' => [
                'servers' => ['web1', 'web2', 'db1'],
                'settings' => [
                    'timeout' => 30,
                    'retry' => true,
                ],
            ],
        ];
        $options = new EncodeOptions(delimiter: '|');

        $encoded = Toon::encode($data, $options);
        $decoded = Toon::decode($encoded);
        $reencoded = Toon::encode($decoded, $options);

        $this->assertEquals($data, $decoded, 'Decoded data should match original');
        $this->assertSame($encoded, $reencoded, 'Re-encoded output should match original encoding');
    }

    // ========================================
    // Section F: v3.0 Tabular List-Item Pattern
    // ========================================

    public function test_tabular_first_field_in_list_item_round_trip(): void
    {
        // v3.0 spec §10: tabular array as first field of list-item object
        // Rows at depth +2, sibling fields at depth +1
        $data = [
            'items' => [[
                'users' => [
                    ['id' => 1, 'name' => 'Ada'],
                    ['id' => 2, 'name' => 'Bob'],
                ],
                'status' => 'active',
            ]],
        ];

        $encoded = Toon::encode($data);
        $decoded = Toon::decode($encoded);
        $reencoded = Toon::encode($decoded);

        $this->assertEquals($data, $decoded, 'Decoded data should match original');
        $this->assertSame($encoded, $reencoded, 'Re-encoded output should match original encoding');

        // Verify the v3.0 indentation pattern (rows at 6 spaces, sibling at 4)
        $this->assertStringContainsString("      1,Ada\n", $encoded);
        $this->assertStringContainsString("      2,Bob\n", $encoded);
        $this->assertStringContainsString("    status: active", $encoded);
    }

    // ========================================
    // Section G: Primitives Round-Trip
    // ========================================

    public function test_primitives_survive_round_trip(): void
    {
        $values = [42, 3.14, 'hello', true, false, null, '', 0, -1];

        foreach ($values as $value) {
            $encoded = Toon::encode($value);
            $decoded = Toon::decode($encoded);
            $reencoded = Toon::encode($decoded);

            $this->assertSame($encoded, $reencoded, "Primitive {$encoded} should survive round-trip");
        }
    }
}
