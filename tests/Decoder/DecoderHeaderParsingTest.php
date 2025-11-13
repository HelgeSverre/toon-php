<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests\Decoder;

use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

/**
 * Tests for decoder header parsing with different delimiters and length markers.
 *
 * These tests verify that the decoder can parse all valid header formats
 * that the encoder produces according to the TOON specification.
 */
final class DecoderHeaderParsingTest extends TestCase
{
    // ========================================
    // Section A: Inline Array Headers
    // ========================================

    public function test_decoder_parses_inline_array_with_comma_default(): void
    {
        $toon = '[3]: a,b,c';
        $expected = ['a', 'b', 'c'];

        $result = Toon::decode($toon);

        $this->assertEquals($expected, $result);
    }

    public function test_decoder_parses_inline_array_with_pipe_delimiter(): void
    {
        $toon = '[3|]: a|b|c';
        $expected = ['a', 'b', 'c'];

        $result = Toon::decode($toon);

        $this->assertEquals($expected, $result);
    }

    public function test_decoder_parses_inline_array_with_tab_delimiter(): void
    {
        $toon = "[3\t]: a\tb\tc";
        $expected = ['a', 'b', 'c'];

        $result = Toon::decode($toon);

        $this->assertEquals($expected, $result);
    }

    // ========================================
    // Section B: Keyed Inline Arrays
    // ========================================

    public function test_decoder_parses_keyed_inline_array_with_comma(): void
    {
        $toon = 'items[2]: a,b';
        $expected = ['items' => ['a', 'b']];

        $result = Toon::decode($toon);

        $this->assertEquals($expected, $result);
    }

    public function test_decoder_parses_keyed_inline_array_with_pipe(): void
    {
        $toon = 'items[2|]: a|b';
        $expected = ['items' => ['a', 'b']];

        $result = Toon::decode($toon);

        $this->assertEquals($expected, $result);
    }

    public function test_decoder_parses_keyed_inline_array_with_tab(): void
    {
        $toon = "items[2\t]: a\tb";
        $expected = ['items' => ['a', 'b']];

        $result = Toon::decode($toon);

        $this->assertEquals($expected, $result);
    }

    // ========================================
    // Section C: List Array Headers
    // ========================================

    public function test_decoder_parses_list_array_header_with_comma(): void
    {
        $toon = "[2]:\n  - a\n  - b";
        $expected = ['a', 'b'];

        $result = Toon::decode($toon);

        $this->assertEquals($expected, $result);
    }

    public function test_decoder_parses_list_array_header_with_pipe(): void
    {
        $toon = "[2|]:\n  - a\n  - b";
        $expected = ['a', 'b'];

        $result = Toon::decode($toon);

        $this->assertEquals($expected, $result);
    }

    public function test_decoder_parses_list_array_header_with_tab(): void
    {
        $toon = "[2\t]:\n  - a\n  - b";
        $expected = ['a', 'b'];

        $result = Toon::decode($toon);

        $this->assertEquals($expected, $result);
    }

    // ========================================
    // Section D: Tabular Array Headers
    // ========================================

    public function test_decoder_parses_tabular_array_with_comma(): void
    {
        $toon = "[2]{id,name}:\n  1,Alice\n  2,Bob";
        $expected = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];

        $result = Toon::decode($toon);

        $this->assertEquals($expected, $result);
    }

    public function test_decoder_parses_tabular_array_with_pipe(): void
    {
        $toon = "[2|]{id|name}:\n  1|Alice\n  2|Bob";
        $expected = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];

        $result = Toon::decode($toon);

        $this->assertEquals($expected, $result);
    }

    public function test_decoder_parses_tabular_array_with_tab(): void
    {
        $toon = "[2\t]{id\tname}:\n  1\tAlice\n  2\tBob";
        $expected = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];

        $result = Toon::decode($toon);

        $this->assertEquals($expected, $result);
    }

    // ========================================
    // Section E: Empty Arrays
    // ========================================

    public function test_decoder_parses_empty_inline_array_with_pipe(): void
    {
        $toon = 'items[0|]:';
        $expected = ['items' => []];

        $result = Toon::decode($toon);

        $this->assertEquals($expected, $result);
    }

    public function test_decoder_parses_empty_inline_array_with_tab(): void
    {
        $toon = "items[0\t]:";
        $expected = ['items' => []];

        $result = Toon::decode($toon);

        $this->assertEquals($expected, $result);
    }
}
