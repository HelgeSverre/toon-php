<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use HelgeSverre\Toon\Constants;
use HelgeSverre\Toon\EncodeOptions;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EncodeOptionsTest extends TestCase
{
    public function test_default_creates_options_with_default_values(): void
    {
        $options = EncodeOptions::default();

        $this->assertEquals(2, $options->indent);
        $this->assertEquals(Constants::DEFAULT_DELIMITER, $options->delimiter);
        $this->assertEquals(false, $options->lengthMarker);
    }

    public function test_constructor_validates_negative_indent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Indent must be non-negative');
        new EncodeOptions(indent: -1);
    }

    public function test_constructor_validates_invalid_delimiter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid delimiter');
        new EncodeOptions(delimiter: ';');
    }

    public function test_constructor_validates_invalid_length_marker(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Length marker must be "#" or false');
        new EncodeOptions(lengthMarker: '%');
    }

    public function test_with_indent_creates_new_instance_with_updated_indent(): void
    {
        $options = EncodeOptions::default();
        $updated = $options->withIndent(4);

        $this->assertEquals(4, $updated->indent);
        $this->assertEquals($options->delimiter, $updated->delimiter);
        $this->assertEquals($options->lengthMarker, $updated->lengthMarker);
        $this->assertEquals(2, $options->indent); // original unchanged
    }

    public function test_with_indent_validates_negative_indent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Indent must be non-negative');
        $options = EncodeOptions::default();
        $options->withIndent(-1);
    }

    public function test_with_delimiter_creates_new_instance_with_updated_delimiter(): void
    {
        $options = EncodeOptions::default();
        $updated = $options->withDelimiter("\t");

        $this->assertEquals("\t", $updated->delimiter);
        $this->assertEquals($options->indent, $updated->indent);
        $this->assertEquals($options->lengthMarker, $updated->lengthMarker);
        $this->assertEquals(',', $options->delimiter); // original unchanged
    }

    public function test_with_delimiter_validates_invalid_delimiter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid delimiter');
        $options = EncodeOptions::default();
        $options->withDelimiter(';');
    }

    public function test_with_length_marker_creates_new_instance_with_updated_length_marker(): void
    {
        $options = EncodeOptions::default();
        $updated = $options->withLengthMarker('#');

        $this->assertEquals('#', $updated->lengthMarker);
        $this->assertEquals($options->indent, $updated->indent);
        $this->assertEquals($options->delimiter, $updated->delimiter);
        $this->assertEquals(false, $options->lengthMarker); // original unchanged
    }

    public function test_with_length_marker_can_disable_length_marker(): void
    {
        $options = new EncodeOptions(lengthMarker: '#');
        $updated = $options->withLengthMarker(false);

        $this->assertEquals(false, $updated->lengthMarker);
        $this->assertEquals('#', $options->lengthMarker); // original unchanged
    }

    public function test_with_length_marker_validates_invalid_length_marker(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Length marker must be "#" or false');
        $options = EncodeOptions::default();
        $options->withLengthMarker('%');
    }

    public function test_chaining_multiple_with_methods(): void
    {
        $options = EncodeOptions::default()
            ->withIndent(4)
            ->withDelimiter('|')
            ->withLengthMarker('#');

        $this->assertEquals(4, $options->indent);
        $this->assertEquals('|', $options->delimiter);
        $this->assertEquals('#', $options->lengthMarker);
    }
}
