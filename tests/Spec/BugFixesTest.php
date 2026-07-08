<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests\Spec;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\Exceptions\CountMismatchException;
use HelgeSverre\Toon\Exceptions\SyntaxException;
use HelgeSverre\Toon\Toon;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Regression suite for the spec deviations catalogued in docs/BUGS.md
 * (TOON-001, TOON-004..TOON-012, TOON-014) verified against TOON v3.3.
 *
 * Test data uses real PHP values in providers to avoid JSON-escaping ambiguity.
 */
final class BugFixesTest extends TestCase
{
    /**
     * @dataProvider decodeCases
     */
    public function test_decode(string $toon, bool $strict, mixed $expected): void
    {
        $this->assertSame($expected, Toon::decode($toon, new DecodeOptions(strict: $strict)));
    }

    /**
     * @return array<string, array{0: string, 1: bool, 2: mixed}>
     */
    public static function decodeCases(): array
    {
        return [
            // --- Cluster A: single-line list items (TOON-004/005/006, §9.2/§9.4/§10) ---
            'A: inner array on hyphen line' => ["[1]:\n  - [2]: 1,2", true, [[1, 2]]],
            'A: two single-field objects' => ["[2]:\n  - a: 1\n  - b: 2", true, [['a' => 1], ['b' => 2]]],
            'A: bare hyphens = empty objects' => ["[2]:\n  -\n  -", true, [[], []]],
            'A: single-field object item' => ["[1]:\n  - a: 1", true, [['a' => 1]]],
            'A: single-element inner array' => ["[1]:\n  - [1]: 9", true, [[9]]],
            'A: empty inner array on hyphen' => ["[1]:\n  - [0]:", true, [[]]],
            'A: object value with quoted colon' => ["[1]:\n  - a: \"x: y\"", true, [['a' => 'x: y']]],
            'A: mixed primitive/array/object' => ["[3]:\n  - hello\n  - [2]: 1,2\n  - a: 1", true, ['hello', [1, 2], ['a' => 1]]],
            'A: object with keyed inline array field' => ["[1]:\n  - items[2]: 1,2", true, [['items' => [1, 2]]]],
            'A: multi-field object first-field-on-hyphen' => ["[1]:\n  - a: 1\n    b: 2", true, [['a' => 1, 'b' => 2]]],
            // regression pins (these already worked; must stay working)
            'A-reg: quoted primitive with colon' => ["[1]:\n  - \"x: y\"", true, ['x: y']],
            'A-reg: quoted bracket primitive' => ["[1]:\n  - \"[literal]\"", true, ['[literal]']],
            'A-reg: plain primitive item' => ["[1]:\n  - hello", true, ['hello']],
            'A-reg: quoted hyphen is a string' => ["[1]:\n  - \"-\"", true, ['-']],
            'A: inner count mismatch (lenient tolerates)' => ["[1]:\n  - [3]: 1,2", false, [[1, 2]]],

            // --- Cluster B: empty document + bare key (TOON-001/008, §5/§8) ---
            'B: empty doc strict' => ['', true, []],
            'B: empty doc lenient' => ['', false, []],
            'B: newline-only doc' => ["\n", true, []],
            'B: whitespace-only doc' => ['   ', true, []],
            'B: multi whitespace lines' => ["   \n  \n   ", true, []],
            'B: blank lines lenient' => ["\n\n", false, []],
            'B: bare key = empty object' => ['key:', true, ['key' => []]],
            'B: bare key lenient' => ['key:', false, ['key' => []]],
            'B: key with value untouched' => ['key: value', true, ['key' => 'value']],
            'B: explicit empty array field' => ['key: []', true, ['key' => []]],
            'B: bare key opens nested object' => ["user:\n  name: Alice\n  age: 30", true, ['user' => ['name' => 'Alice', 'age' => 30]]],
            'B: trailing bare-key sibling' => ["a: 1\nb:", true, ['a' => 1, 'b' => []]],
            'B: bare key then same-depth sibling' => ["meta:\nname: Bob", true, ['meta' => [], 'name' => 'Bob']],
            'B: nested bare key' => ["parent:\n  child:", true, ['parent' => ['child' => []]]],
            'B: legacy empty array header' => ['key[0]:', true, ['key' => []]],

            // --- Cluster C: quoted key in header (TOON-009, §7.4) ---
            'C: quoted header key unescaped' => ['"my-key"[3]: 1,2,3', true, ['my-key' => [1, 2, 3]]],
            'C: quoted header key with space' => ['"full name"[2]: a,b', true, ['full name' => ['a', 'b']]],
            'C: unquoted header key unchanged' => ['items[2]: a,b', true, ['items' => ['a', 'b']]],

            // --- Cluster D: empty tokens (TOON-010, §9.1/§11.2) ---
            'D: empty middle token' => ['[3]: a,,b', true, ['a', '', 'b']],
            'D: empty middle token lenient' => ['[3]: a,,b', false, ['a', '', 'b']],
            'D: empty leading token' => ['[2]: ,a', true, ['', 'a']],
            'D: empty trailing token' => ['[2]: a,', true, ['a', '']],
            'D: all empty tokens' => ['[3]: ,,', true, ['', '', '']],
            'D: explicit quoted empty' => ['[3]: a,"",c', true, ['a', '', 'c']],
            'D: tabular empty cells' => ["[2]{a,b}:\n  1,\n  ,2", true, [['a' => 1, 'b' => ''], ['a' => '', 'b' => 2]]],

            // --- Cluster F: relabelled conformant forms (still MUST decode) ---
            'F: root empty array literal' => ['[]', true, []],
            'F: legacy empty array field' => ['tags[0]:', true, ['tags' => []]],
            'F: canonical empty array field' => ['tags: []', true, ['tags' => []]],
        ];
    }

    /**
     * @dataProvider decodeThrowsCases
     *
     * @param  class-string<\Throwable>  $exception
     */
    public function test_decode_throws(string $toon, bool $strict, string $exception): void
    {
        $this->expectException($exception);
        Toon::decode($toon, new DecodeOptions(strict: $strict));
    }

    /**
     * @return array<string, array{0: string, 1: bool, 2: class-string<\Throwable>}>
     */
    public static function decodeThrowsCases(): array
    {
        return [
            'A: inner count mismatch strict' => ["[1]:\n  - [3]: 1,2", true, CountMismatchException::class],
            'A: outer count mismatch strict' => ["[2]:\n  - a: 1", true, CountMismatchException::class],
            'D: invalid escape inside quotes' => ['[1]: "a\\,b"', true, SyntaxException::class],
            'D: unterminated quoted token' => ['[1]: "unterminated', true, SyntaxException::class],
        ];
    }

    // --- Cluster D: escape-heavy cases (explicit to keep the raw bytes obvious) ---

    public function test_toon011_key_ending_in_backslash(): void
    {
        // Raw TOON:  "a\\": c   (key "a\\" unescapes to a-backslash)
        $toon = '"a\\\\": c';
        $this->assertSame(['a\\' => 'c'], Toon::decode($toon));
    }

    public function test_colon_inside_quoted_key_is_literal(): void
    {
        $this->assertSame(['a:b' => 'v'], Toon::decode('"a:b": v'));
    }

    public function test_escaped_quote_inside_quoted_key(): void
    {
        // Raw TOON:  "a\"b": v   (key a"b)
        $this->assertSame(['a"b' => 'v'], Toon::decode('"a\\"b": v'));
    }

    public function test_toon012_unquoted_backslash_before_comma(): void
    {
        // Raw TOON:  [2]: a\,b   (unquoted backslash is literal; comma splits)
        $this->assertSame(['a\\', 'b'], Toon::decode('[2]: a\\,b'));
    }

    public function test_toon012_unquoted_backslash_before_pipe(): void
    {
        $this->assertSame(['a\\', 'b'], Toon::decode('[2|]: a\\|b'));
    }

    public function test_toon012_unquoted_backslash_before_tab(): void
    {
        $this->assertSame(['a\\', 'b'], Toon::decode("[2\t]: a\\\tb"));
    }

    public function test_quoted_escaped_backslash_still_unescapes(): void
    {
        // Raw TOON:  [1]: "a\\b"   -> a-backslash-b
        $this->assertSame(['a\\b'], Toon::decode('[1]: "a\\\\b"'));
    }

    // --- Cluster E: option validation (TOON-014, §12) ---

    public function test_encode_options_reject_zero_indent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EncodeOptions(indent: 0);
    }

    public function test_decode_options_reject_zero_indent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DecodeOptions(indent: 0);
    }

    public function test_encode_options_reject_negative_indent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EncodeOptions(indent: -1);
    }

    public function test_decode_options_reject_negative_indent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DecodeOptions(indent: -2);
    }

    public function test_compact_preset_preserves_nesting(): void
    {
        // EncodeOptions::compact() retargeted to indent:1 (was 0, which collapsed nesting)
        $encoded = Toon::encode(['a' => ['b' => 1]], EncodeOptions::compact());
        $this->assertSame("a:\n b: 1", $encoded);
        $this->assertSame(['a' => ['b' => 1]], Toon::decode($encoded, new DecodeOptions(indent: 1)));
    }

    // --- Cluster F: empty-object list item emits a bare hyphen (TOON-007, §10/§12) ---

    public function test_empty_object_list_item_has_no_trailing_space(): void
    {
        // Unreachable via public API, so exercise the private encoder path directly.
        $writer = new \HelgeSverre\Toon\LineWriter('  ');
        $encoder = new \HelgeSverre\Toon\Encoders(new EncodeOptions, $writer);
        $method = new ReflectionMethod($encoder, 'encodeObjectAsListItem');
        $method->setAccessible(true);
        $method->invoke($encoder, [], 0);
        $this->assertSame('-', $writer->toString());
    }

    /**
     * @dataProvider encodeCases
     */
    public function test_encode(mixed $value, ?EncodeOptions $options, string $expected): void
    {
        $this->assertSame($expected, Toon::encode($value, $options));
    }

    /**
     * @return array<string, array{0: mixed, 1: ?EncodeOptions, 2: string}>
     */
    public static function encodeCases(): array
    {
        return [
            'enc: array of arrays' => [[[1, 2], [3, 4]], null, "[2]:\n  - [2]: 1,2\n  - [2]: 3,4"],
            'enc: non-uniform objects' => [[['a' => 1], ['b' => 2]], null, "[2]:\n  - a: 1\n  - b: 2"],
            'enc: empty-string element quoted' => [['a', '', 'b'], null, '[3]: a,"",b'],
            'enc: backslash value quoted' => [['a\\', 'b'], null, '[2]: "a\\\\",b'],
            'enc: backslash key' => [['a\\' => 'c'], null, '"a\\\\": c'],
            'enc: indent 1 preserves nesting' => [['a' => ['b' => 1]], new EncodeOptions(indent: 1), "a:\n b: 1"],
            'enc: indent 4' => [['a' => ['b' => 1]], new EncodeOptions(indent: 4), "a:\n    b: 1"],
            'enc: default indent 2' => [['a' => ['b' => 1]], null, "a:\n  b: 1"],
            'enc: compact flat' => [['id' => 1, 'name' => 'Bob', 'active' => true], EncodeOptions::compact(), "id: 1\nname: Bob\nactive: true"],
        ];
    }

    /**
     * @dataProvider roundtripCases
     */
    public function test_roundtrip(mixed $value, ?EncodeOptions $enc, ?DecodeOptions $dec): void
    {
        $encoded = Toon::encode($value, $enc);
        $decoded = Toon::decode($encoded, $dec ?? new DecodeOptions);
        $this->assertSame($value, $decoded);
    }

    /**
     * @return array<string, array{0: mixed, 1: ?EncodeOptions, 2: ?DecodeOptions}>
     */
    public static function roundtripCases(): array
    {
        return [
            'rt: array of arrays' => [[[1, 2], [3, 4]], null, null],
            'rt: non-uniform objects' => [[['a' => 1], ['b' => 2]], null, null],
            'rt: single-element inner array' => [[[9]], null, null],
            'rt: empty inner array' => [[[]], null, null],
            'rt: mixed array' => [['hello', [1, 2], ['a' => 1]], null, null],
            'rt: empty array field' => [['key' => []], null, null],
            'rt: backslash inline value' => [['a\\', 'b'], null, null],
            'rt: empty string element' => [['a', '', 'b'], null, null],
            'rt: backslash key' => [['a\\' => 'c'], null, null],
            'rt: root scalar backslash' => ['a\\', null, null],
            'rt: backslash mid-value' => [['x\\y'], null, null],
            'rt: quoted-needing key with array' => [['my-key' => [1, 2, 3]], null, null],
            'rt: nested array of objects' => [[[['x' => 1, 'y' => 2], ['x' => 3, 'y' => 4]]], null, null],
            'rt: tabular-first-field list item' => [[['items' => [['a' => 1, 'b' => 2], ['a' => 3, 'b' => 4]], 'status' => 'ok']], null, null],
            'rt: compact nested' => [['a' => ['b' => 1]], EncodeOptions::compact(), new DecodeOptions(indent: 1)],
            'rt: custom indent nested' => [['a' => ['b' => 1], 'c' => ['d' => ['e' => 2]]], new EncodeOptions(indent: 3), new DecodeOptions(indent: 3)],
        ];
    }

    // --- validate()/decode() parity for the fixed inputs (Validator mirrors Parser) ---

    /**
     * @dataProvider parityCases
     */
    public function test_validate_matches_decode(string $toon): void
    {
        $decodeOk = true;
        try {
            Toon::decode($toon);
        } catch (\Throwable) {
            $decodeOk = false;
        }
        $this->assertSame($decodeOk, Toon::validate($toon), "validate() must agree with decode() for: $toon");
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function parityCases(): array
    {
        return [
            'empty doc' => [''],
            'bare key' => ['key:'],
            'empty tokens' => ['[3]: a,,b'],
            'quoted key colon' => ['"a:b": v'],
            'inner array hyphen' => ["[1]:\n  - [2]: 1,2"],
            'object list' => ["[2]:\n  - a: 1\n  - b: 2"],
        ];
    }
}
