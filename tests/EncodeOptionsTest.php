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

    public function test_compact_creates_options_with_zero_indent(): void
    {
        $options = EncodeOptions::compact();

        $this->assertEquals(0, $options->indent);
        $this->assertEquals(',', $options->delimiter);
        $this->assertEquals(false, $options->lengthMarker);
    }

    public function test_readable_creates_options_with_four_space_indent(): void
    {
        $options = EncodeOptions::readable();

        $this->assertEquals(4, $options->indent);
        $this->assertEquals(',', $options->delimiter);
        $this->assertEquals(false, $options->lengthMarker);
    }

    public function test_tabular_creates_options_with_tab_delimiter(): void
    {
        $options = EncodeOptions::tabular();

        $this->assertEquals(2, $options->indent);
        $this->assertEquals("\t", $options->delimiter);
        $this->assertEquals(false, $options->lengthMarker);
    }

    public function test_with_length_markers_creates_options_with_hash_prefix(): void
    {
        $options = EncodeOptions::withLengthMarkers();

        $this->assertEquals(2, $options->indent);
        $this->assertEquals(',', $options->delimiter);
        $this->assertEquals('#', $options->lengthMarker);
    }

    public function test_pipe_delimited_creates_options_with_pipe_delimiter(): void
    {
        $options = EncodeOptions::pipeDelimited();

        $this->assertEquals(2, $options->indent);
        $this->assertEquals('|', $options->delimiter);
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

    public function test_length_marker_with_tab_delimiter_combined(): void
    {
        // P1 High Priority: Test combining length marker with tab delimiter
        // Both options should work together correctly
        $input = ['tags' => ['reading', 'gaming', 'coding']];
        $options = new EncodeOptions(lengthMarker: '#', delimiter: "\t");
        $expected = "tags[#3\t]: reading\tgaming\tcoding";

        $result = \HelgeSverre\Toon\Toon::encode($input, $options);
        $this->assertEquals($expected, $result);
    }

    // Phase 3.2: Feature Combinations

    public function test_encode_zero_indent_with_deeply_nested_structure(): void
    {
        // Test indent: 0 with 5-level nesting
        $input = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => [
                            'level5' => 'deep value',
                        ],
                    ],
                ],
            ],
        ];

        $options = new EncodeOptions(indent: 0);
        $result = \HelgeSverre\Toon\Toon::encode($input, $options);

        // With zero indentation, all lines should start at column 0
        $expected = "level1:\nlevel2:\nlevel3:\nlevel4:\nlevel5: deep value";
        $this->assertEquals($expected, $result);
    }

    public function test_encode_pipe_delimiter_with_unicode_and_escapes(): void
    {
        // Combine pipe delimiter with Unicode and escaped characters
        $input = [
            'items' => ['café', 'naïve', "line\nbreak", "tab\there"],
        ];

        $options = new EncodeOptions(delimiter: '|');
        $result = \HelgeSverre\Toon\Toon::encode($input, $options);

        // Strings requiring escapes should be quoted
        $expected = 'items[4|]: café|naïve|"line\\nbreak"|"tab\\there"';
        $this->assertEquals($expected, $result);
    }

    public function test_encode_tab_delimiter_with_length_markers_and_nesting(): void
    {
        // Combine tab delimiter, length markers, and nested structures
        $input = [
            'users' => [
                ['id' => 1, 'tags' => ['admin', 'active']],
                ['id' => 2, 'tags' => ['user', 'guest']],
            ],
        ];

        $options = new EncodeOptions(delimiter: "\t", lengthMarker: '#');
        $result = \HelgeSverre\Toon\Toon::encode($input, $options);

        // Length marker and delimiter apply to the nested inline arrays
        $this->assertStringContainsString('users[2]:', $result);
        $this->assertStringContainsString("tags[#2\t]:", $result); // Nested array uses length marker + tab
        $this->assertStringContainsString("admin\tactive", $result); // Tab delimiter used
        $this->assertStringContainsString("user\tguest", $result);
    }

    public function test_encode_datetime_array_in_tabular_format(): void
    {
        // DateTime objects in tabular array (normalization + tabular)
        $input = [
            'events' => [
                ['id' => 1, 'date' => new \DateTime('2025-01-01T10:00:00', new \DateTimeZone('UTC'))],
                ['id' => 2, 'date' => new \DateTime('2025-01-02T11:00:00', new \DateTimeZone('UTC'))],
            ],
        ];

        $result = \HelgeSverre\Toon\Toon::encode($input);

        // DateTime should be normalized to ISO 8601 strings
        $this->assertStringContainsString('events[2]{id,date}:', $result);
        $this->assertStringContainsString('2025-01-01', $result);
        $this->assertStringContainsString('2025-01-02', $result);
        // Dates should be quoted as they contain special chars
        $this->assertMatchesRegularExpression('/"\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $result);
    }

    public function test_encode_enum_array_in_tabular_format(): void
    {
        // Enum values in tabular array (normalization + tabular)
        $input = [
            'records' => [
                ['id' => 1, 'status' => EncodeStatus::ACTIVE],
                ['id' => 2, 'status' => EncodeStatus::INACTIVE],
            ],
        ];

        $result = \HelgeSverre\Toon\Toon::encode($input);

        // Enums should be normalized to their backing values (tabular format without hyphens)
        $expected = "records[2]{id,status}:\n  1,active\n  2,inactive";
        $this->assertEquals($expected, $result);
    }

    public function test_encode_all_special_values_in_tabular_array(): void
    {
        // All special values in tabular format
        $input = [
            'data' => [
                ['id' => 1, 'value' => null, 'flag' => true],
                ['id' => 2, 'value' => 0, 'flag' => false],
                ['id' => 3, 'value' => INF, 'flag' => true],
            ],
        ];

        $result = \HelgeSverre\Toon\Toon::encode($input);

        // Special values: null, true, false, and INF (normalized to null)
        $this->assertStringContainsString('data[3]{id,value,flag}:', $result);
        $this->assertStringContainsString('null', $result);
        $this->assertStringContainsString('true', $result);
        $this->assertStringContainsString('false', $result);
    }

    public function test_encode_all_special_values_in_inline_array(): void
    {
        // Same special values but inline format
        $input = ['values' => [null, true, false, 0, INF, -INF, NAN]];

        $result = \HelgeSverre\Toon\Toon::encode($input);

        // INF, -INF, NAN normalized to null
        $expected = 'values[7]: null,true,false,0,null,null,null';
        $this->assertEquals($expected, $result);
    }

    public function test_encode_all_special_values_as_object_values(): void
    {
        // Special values as object field values
        $input = [
            'nullable' => null,
            'positive' => true,
            'negative' => false,
            'zero' => 0,
            'infinite' => INF,
            'negInfinite' => -INF,
            'notANumber' => NAN,
        ];

        $result = \HelgeSverre\Toon\Toon::encode($input);

        // Verify each special value is encoded correctly
        $lines = explode("\n", $result);
        $this->assertContains('nullable: null', $lines);
        $this->assertContains('positive: true', $lines);
        $this->assertContains('negative: false', $lines);
        $this->assertContains('zero: 0', $lines);
        $this->assertContains('infinite: null', $lines); // INF -> null
        $this->assertContains('negInfinite: null', $lines); // -INF -> null
        $this->assertContains('notANumber: null', $lines); // NAN -> null
    }
}

enum EncodeStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
