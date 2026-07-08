<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use HelgeSverre\Toon\DecodeOptions;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DecodeOptionsTest extends TestCase
{
    public function test_default_creates_options_with_default_values(): void
    {
        $options = DecodeOptions::default();

        $this->assertEquals(2, $options->indent);
        $this->assertEquals(true, $options->strict);
    }

    public function test_lenient_creates_options_with_strict_disabled(): void
    {
        $options = DecodeOptions::lenient();

        $this->assertEquals(2, $options->indent);
        $this->assertEquals(false, $options->strict);
    }

    public function test_constructor_validates_negative_indent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Indent must be a positive integer (at least 1)');
        new DecodeOptions(indent: -1);
    }

    public function test_constructor_rejects_zero_indent(): void
    {
        // indent 0 makes every line depth 0, so nesting is unrecoverable (§12).
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Indent must be a positive integer (at least 1)');
        new DecodeOptions(indent: 0);
    }

    public function test_constructor_accepts_custom_indent(): void
    {
        $options = new DecodeOptions(indent: 4);

        $this->assertEquals(4, $options->indent);
        $this->assertEquals(true, $options->strict);
    }

    public function test_constructor_accepts_strict_false(): void
    {
        $options = new DecodeOptions(strict: false);

        $this->assertEquals(2, $options->indent);
        $this->assertEquals(false, $options->strict);
    }

    public function test_with_indent_creates_new_instance_with_updated_indent(): void
    {
        $options = DecodeOptions::default();
        $updated = $options->withIndent(4);

        $this->assertEquals(4, $updated->indent);
        $this->assertEquals($options->strict, $updated->strict);
        $this->assertEquals(2, $options->indent); // original unchanged
    }

    public function test_with_indent_validates_negative_indent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Indent must be a positive integer (at least 1)');
        $options = DecodeOptions::default();
        $options->withIndent(-1);
    }

    public function test_with_strict_creates_new_instance_with_updated_strict(): void
    {
        $options = DecodeOptions::default();
        $updated = $options->withStrict(false);

        $this->assertEquals(false, $updated->strict);
        $this->assertEquals($options->indent, $updated->indent);
        $this->assertEquals(true, $options->strict); // original unchanged
    }

    public function test_with_strict_can_enable_strict_mode(): void
    {
        $options = DecodeOptions::lenient();
        $updated = $options->withStrict(true);

        $this->assertEquals(true, $updated->strict);
        $this->assertEquals(false, $options->strict); // original unchanged
    }

    public function test_chaining_multiple_with_methods(): void
    {
        $options = DecodeOptions::default()
            ->withIndent(4)
            ->withStrict(false);

        $this->assertEquals(4, $options->indent);
        $this->assertEquals(false, $options->strict);
    }

    public function test_lenient_mode_allows_flexible_parsing(): void
    {
        // Test that lenient mode actually works with real decoding
        // Using inline array with count mismatch at root level
        $toon = '[3]: a,b'; // Count mismatch: says 3 but has 2

        $options = DecodeOptions::lenient();
        $result = \HelgeSverre\Toon\Toon::decode($toon, $options);

        // Should parse successfully in lenient mode
        $this->assertEquals(['a', 'b'], $result);
    }

    public function test_strict_mode_rejects_count_mismatch(): void
    {
        // Test that strict mode enforces count matching
        $toon = '[3]: a,b'; // Count mismatch: says 3 but has 2

        $this->expectException(\HelgeSverre\Toon\Exceptions\CountMismatchException::class);

        $options = DecodeOptions::default(); // strict by default
        \HelgeSverre\Toon\Toon::decode($toon, $options);
    }

    public function test_custom_indent_with_strict_mode(): void
    {
        // Test custom indent setting works correctly
        $toon = "outer:\n    nested: value"; // 4-space indent

        $options = new DecodeOptions(indent: 4, strict: true);
        $result = \HelgeSverre\Toon\Toon::decode($toon, $options);

        $this->assertEquals(['outer' => ['nested' => 'value']], $result);
    }

    public function test_zero_indent_rejected_even_in_lenient_mode(): void
    {
        // indent 0 is invalid regardless of strict mode.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Indent must be a positive integer (at least 1)');
        new DecodeOptions(indent: 0, strict: false);
    }
}
