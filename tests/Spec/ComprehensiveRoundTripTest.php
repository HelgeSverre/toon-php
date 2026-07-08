<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests\Spec;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end round-trip coverage over a broad corpus of data shapes, exercised
 * in both directions:
 *
 *   A. value -> encode -> decode -> value          (across every option preset)
 *   B. toon  -> decode -> encode -> toon           (re-encode is idempotent)
 *   C. hand-written canonical TOON -> decode -> encode -> same TOON
 *
 * Together these verify the encoder and decoder are mutual inverses on the
 * encoder's canonical form for primitives, nested objects, inline arrays,
 * arrays-of-arrays, tabular rows, list format, and empty collections.
 */
final class ComprehensiveRoundTripTest extends TestCase
{
    /**
     * Encode/decode option pairs. Decode indent must match encode indent so the
     * decoder recovers nesting depth; delimiter is auto-detected from headers.
     *
     * @return array<string, array{0: EncodeOptions, 1: DecodeOptions}>
     */
    private static function presetPairs(): array
    {
        return [
            'default' => [EncodeOptions::default(), new DecodeOptions(indent: 2)],
            'compact' => [EncodeOptions::compact(), new DecodeOptions(indent: 1)],
            'readable' => [EncodeOptions::readable(), new DecodeOptions(indent: 4)],
            'tabular-tab' => [EncodeOptions::tabular(), new DecodeOptions(indent: 2)],
            'pipe' => [EncodeOptions::pipeDelimited(), new DecodeOptions(indent: 2)],
        ];
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function fixtures(): array
    {
        $esc = "\x1b";

        return [
            // --- primitives ---
            'int' => [42],
            'negative int' => [-7],
            'zero' => [0],
            'float' => [3.14],
            'float precision' => [0.1 + 0.2],
            'tiny exponent' => [1e-7],
            'huge exponent' => [1e21],
            'true' => [true],
            'false' => [false],
            'null' => [null],
            'empty string' => [''],
            'plain string' => ['hello world'],
            'unicode' => ['héllo — wörld ✓'],
            'emoji' => ['🚀🔥'],
            'numeric string' => ['42'],
            'reserved word string' => ['true'],
            'newline string' => ["line1\nline2"],
            'tab string' => ["a\tb"],
            'ansi string' => ['start'.$esc.'[31merr'.$esc.'[0m'],
            'bracket string' => ['a[3]b'],

            // --- flat object ---
            'flat object' => [['id' => 1, 'name' => 'Alice', 'active' => true, 'score' => null]],
            'object numeric-ish keys' => [['1' => 'a', '2' => 'b']],
            'object special keys' => [['a:b' => 1, 'x[0]' => 2, 'with space' => 3]],

            // --- nested objects ---
            'nested object' => [['user' => ['id' => 1, 'profile' => ['city' => 'Oslo', 'zip' => '0001']]]],

            // --- inline arrays of primitives ---
            'inline ints' => [['nums' => [1, 2, 3]]],
            'inline mixed' => [['vals' => [1, 'two', true, null, 3.5]]],
            'inline with delimiters' => [['items' => ['a,b', 'c|d', "e\tf"]]],

            // --- arrays of arrays ---
            'array of arrays' => [['matrix' => [[1, 2], [3, 4], [5, 6]]]],

            // --- tabular (uniform object arrays) ---
            'tabular' => [['users' => [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']]]],
            'tabular with quotes needed' => [['rows' => [['a' => 'x,y', 'b' => 'p|q'], ['a' => 'm', 'b' => 'n']]]],

            // --- list format (non-uniform) ---
            'list non-uniform' => [['items' => [['id' => 1], ['id' => 2, 'extra' => 'x']]]],
            'list with nested arrays' => [['items' => [['name' => 'A', 'tags' => ['x', 'y']], ['name' => 'B', 'tags' => ['z']]]]],

            // --- empties ---
            'empty array' => [[]],
            'empty nested array' => [['a' => []]],
            'empty string in list' => [['x' => ['', 'a', '']]],

            // --- realistic composite ---
            'composite document' => [[
                'meta' => ['version' => 2, 'tags' => ['a', 'b']],
                'users' => [
                    ['id' => 1, 'name' => 'Alice', 'roles' => ['admin', 'user']],
                    ['id' => 2, 'name' => 'Bob', 'roles' => ['user']],
                ],
                'flags' => [true, false, null],
                'note' => 'contains: comma, pipe | and [brackets]',
            ]],
        ];
    }

    /**
     * Direction A: value -> encode -> decode -> value, in every option preset.
     *
     * @dataProvider fixtures
     */
    public function test_value_round_trips_in_all_presets(mixed $value): void
    {
        foreach (self::presetPairs() as $name => [$encode, $decode]) {
            $toon = Toon::encode($value, $encode);
            $back = Toon::decode($toon, $decode);

            $this->assertSame($value, $back, "Preset '{$name}' broke the round-trip.\nTOON:\n".$toon."\n");
        }
    }

    /**
     * Direction B: encoding is a fixed point through a decode cycle, i.e.
     * encode(decode(encode(v))) === encode(v). This proves the decoder returns
     * exactly what the encoder consumed for the canonical form.
     *
     * @dataProvider fixtures
     */
    public function test_reencode_is_idempotent(mixed $value): void
    {
        $toon = Toon::encode($value);
        $reencoded = Toon::encode(Toon::decode($toon));

        $this->assertSame($toon, $reencoded, "Re-encode drifted.\nfirst:\n".$toon."\n\nsecond:\n".$reencoded."\n");
    }

    /**
     * Direction C (toon -> value): hand-written TOON, including non-canonical
     * and legacy forms, decodes to the expected value. This exercises the
     * decoder on human-authored input rather than only encoder output.
     *
     * @dataProvider handWrittenDocuments
     */
    public function test_decodes_hand_written_toon(string $toon, mixed $expected): void
    {
        $this->assertSame($expected, Toon::decode($toon));
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function handWrittenDocuments(): array
    {
        return [
            'scalar' => ['42', 42],
            'flat object' => ["name: Alice\nage: 30", ['name' => 'Alice', 'age' => 30]],
            'inline array' => ['nums[3]: 1,2,3', ['nums' => [1, 2, 3]]],
            'nested object' => ["user:\n  id: 1\n  name: Alice", ['user' => ['id' => 1, 'name' => 'Alice']]],
            'tabular' => [
                "users[2]{id,name}:\n  1,Alice\n  2,Bob",
                ['users' => [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']]],
            ],
            'list non-uniform' => [
                "items[2]:\n  - id: 1\n  - name: Bob",
                ['items' => [['id' => 1], ['name' => 'Bob']]],
            ],
            'array of arrays' => ["matrix[2]:\n  - [2]: 1,2\n  - [2]: 3,4", ['matrix' => [[1, 2], [3, 4]]]],
            // Directly exercises the bracket/colon/pipe-in-quoted-value fix.
            'quoted structural chars' => ['note: "a, b [c] | d"', ['note' => 'a, b [c] | d']],
            'legacy empty array' => ['[0]:', []],
            'canonical empty array' => ['[]', []],
            'empty via key' => ['x: []', ['x' => []]],
        ];
    }
}
