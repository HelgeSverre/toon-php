<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests\Spec;

use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

/**
 * Tests for TOON Specification v3.0 Breaking Changes.
 *
 * v3.0 Breaking Change (Section 10):
 * - When a list-item object has a tabular array as its first field in encounter order:
 *   - Encoders MUST emit the tabular header on the hyphen line: `- key[N]{fields}:`
 *   - Tabular rows MUST appear at depth +2 (relative to the hyphen line)
 *   - All other fields of the same object MUST appear at depth +1
 */
final class Version3BreakingChangesTest extends TestCase
{
    public function test_encoder_emits_tabular_rows_at_depth_plus_2(): void
    {
        // v3.0: Tabular rows must be at depth +2 from hyphen line
        $input = [
            'items' => [[
                'users' => [
                    ['id' => 1, 'name' => 'Ada'],
                    ['id' => 2, 'name' => 'Bob'],
                ],
                'status' => 'active',
            ]],
        ];

        $result = Toon::encode($input);

        // Hyphen line at 2 spaces, rows at 6 spaces (depth +2)
        $this->assertStringContainsString("  - users[2]{id,name}:\n", $result);
        $this->assertStringContainsString("      1,Ada\n", $result);
        $this->assertStringContainsString("      2,Bob\n", $result);
    }

    public function test_encoder_emits_sibling_fields_at_depth_plus_1(): void
    {
        // v3.0: Sibling fields must be at depth +1 from hyphen line
        $input = [
            'items' => [[
                'users' => [
                    ['id' => 1, 'name' => 'Ada'],
                ],
                'status' => 'active',
                'count' => 1,
            ]],
        ];

        $result = Toon::encode($input);

        // Hyphen line at 2 spaces, siblings at 4 spaces (depth +1)
        $this->assertStringContainsString("    status: active\n", $result);
        $this->assertStringContainsString("    count: 1", $result);
    }

    public function test_decoder_accepts_v3_format(): void
    {
        // v3.0 format: rows at +2, siblings at +1
        $toon = "items[1]:\n  - users[2]{id,name}:\n      1,Ada\n      2,Bob\n    status: active";

        $result = Toon::decode($toon);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertCount(1, $result['items']);

        $item = $result['items'][0];
        $this->assertArrayHasKey('users', $item);
        $this->assertArrayHasKey('status', $item);

        $this->assertEquals([
            ['id' => 1, 'name' => 'Ada'],
            ['id' => 2, 'name' => 'Bob'],
        ], $item['users']);
        $this->assertEquals('active', $item['status']);
    }

    public function test_round_trip_preserves_v3_format(): void
    {
        // v3.0: encode -> decode -> encode should produce identical output
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

        $this->assertEquals($data, $decoded);
        $this->assertSame($encoded, $reencoded);
    }

    public function test_multiple_list_items_with_tabular_first_field(): void
    {
        // v3.0: Multiple list items each with tabular first field
        $input = [
            'groups' => [
                [
                    'members' => [
                        ['id' => 1, 'role' => 'admin'],
                    ],
                    'name' => 'Group A',
                ],
                [
                    'members' => [
                        ['id' => 2, 'role' => 'user'],
                        ['id' => 3, 'role' => 'user'],
                    ],
                    'name' => 'Group B',
                ],
            ],
        ];

        $result = Toon::encode($input);

        // Each list item should have its tabular rows at +2
        $this->assertStringContainsString("  - members[1]{id,role}:\n", $result);
        $this->assertStringContainsString("  - members[2]{id,role}:\n", $result);

        // Verify round-trip
        $decoded = Toon::decode($result);
        $this->assertEquals($input, $decoded);
    }

    public function test_tabular_only_first_field_affected(): void
    {
        // v3.0: Only first field gets special treatment, not all fields
        $input = [
            'items' => [[
                'name' => 'test',
                'users' => [
                    ['id' => 1],
                    ['id' => 2],
                ],
            ]],
        ];

        $result = Toon::encode($input);

        // When tabular is NOT first field, normal depth +1 applies
        // First field 'name' on hyphen line, 'users' header at +1, rows at +2
        $this->assertStringContainsString("  - name: test\n", $result);
        $this->assertStringContainsString("    users[2]{id}:\n", $result);
        $this->assertStringContainsString("      1\n", $result);
        $this->assertStringContainsString("      2", $result);
    }
}
