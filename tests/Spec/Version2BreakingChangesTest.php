<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests\Spec;

use HelgeSverre\Toon\Exceptions\SyntaxException;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

/**
 * Tests for TOON Specification v2.0 Breaking Changes.
 *
 * v2.0 Breaking Change (Appendix D):
 * - Length marker (#) prefix in array headers has been completely removed
 * - Encoders MUST NOT emit [#N] format
 * - Decoders MUST NOT accept [#N] format
 */
final class Version2BreakingChangesTest extends TestCase
{
    public function test_decoder_rejects_hash_n_inline_array(): void
    {
        // v2.0: [#N]: format is INVALID
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('[#N] syntax is not supported in TOON v2.0');

        Toon::decode('[#3]: a,b,c');
    }

    public function test_decoder_rejects_hash_n_list_array(): void
    {
        // v2.0: [#N]: format with list items is INVALID
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('[#N] syntax is not supported in TOON v2.0');

        $toon = "[#2]:\n  - a\n  - b";
        Toon::decode($toon);
    }

    public function test_decoder_rejects_hash_n_tabular_array(): void
    {
        // v2.0: [#N]{fields}: format is INVALID
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('[#N] syntax is not supported in TOON v2.0');

        $toon = "[#2]{id,name}:\n  1,Alice\n  2,Bob";
        Toon::decode($toon);
    }

    public function test_decoder_rejects_hash_n_keyed_array(): void
    {
        // v2.0: key[#N]: format is INVALID
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('[#N] syntax is not supported in TOON v2.0');

        Toon::decode('items[#3]: a,b,c');
    }

    public function test_decoder_rejects_hash_n_with_pipe_delimiter(): void
    {
        // v2.0: [#N|]: format with pipe delimiter is INVALID
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('[#N] syntax is not supported in TOON v2.0');

        Toon::decode('[#3|]: a|b|c');
    }

    public function test_decoder_rejects_hash_n_with_tab_delimiter(): void
    {
        // v2.0: [#N\t]: format with tab delimiter is INVALID
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('[#N] syntax is not supported in TOON v2.0');

        Toon::decode("[#3\t]: a\tb\tc");
    }

    public function test_encoder_never_emits_hash_n_format(): void
    {
        // v2.0: Encoder MUST use [N]: format only
        $input = ['items' => [1, 2, 3]];
        $result = Toon::encode($input);

        // Verify no [# appears in output
        $this->assertStringNotContainsString('[#', $result);

        // Verify correct [N]: format is used
        $this->assertStringContainsString('[3]:', $result);
    }

    public function test_encoder_never_emits_hash_n_in_nested_arrays(): void
    {
        // v2.0: Encoder MUST NOT use [#N]: even in nested structures
        $input = [
            'data' => [
                'items' => ['a', 'b'],
                'values' => [1, 2, 3],
            ],
        ];
        $result = Toon::encode($input);

        // Verify no [# anywhere in output
        $this->assertStringNotContainsString('[#', $result);

        // Verify correct formats
        $this->assertStringContainsString('[2]:', $result);
        $this->assertStringContainsString('[3]:', $result);
    }

    public function test_decoder_rejects_n_hash_pattern(): void
    {
        // v2.0: [N#]: pattern (# after digits) is also INVALID
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('[#N] syntax is not supported in TOON v2.0');

        Toon::decode('[5#]: a,b,c');
    }

    public function test_decoder_rejects_multiple_hashes(): void
    {
        // v2.0: [##N]: pattern is INVALID
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('[#N] syntax is not supported in TOON v2.0');

        Toon::decode('[##5]: a,b,c');
    }
}
