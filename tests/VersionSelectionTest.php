<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\SpecVersion;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

final class VersionSelectionTest extends TestCase
{
    public function test_existing_positional_and_named_options_keep_their_meaning(): void
    {
        $this->assertEquals(new EncodeOptions(4, '|'), new EncodeOptions(indent: 4, delimiter: '|'));
        $this->assertEquals(new DecodeOptions(4, false), new DecodeOptions(indent: 4, strict: false));
        $this->assertSame(SpecVersion::V3_3, (new EncodeOptions)->version);
        $this->assertSame(SpecVersion::V3_3, (new DecodeOptions)->version);
        foreach ([EncodeOptions::default(), EncodeOptions::compact(), EncodeOptions::readable(), EncodeOptions::tabular(), EncodeOptions::pipeDelimited()] as $options) {
            $this->assertSame(SpecVersion::V3_3, $options->version);
        }
        $this->assertSame(SpecVersion::V3_3, DecodeOptions::lenient()->version);
    }

    public function test_option_copies_preserve_version_and_other_settings(): void
    {
        $encode = new EncodeOptions(4, '|');
        $newEncode = $encode->withVersion(SpecVersion::V4_1)->withIndent(1)->withDelimiter("\t");
        $this->assertEquals(new EncodeOptions(1, "\t", SpecVersion::V4_1), $newEncode);
        $this->assertEquals(new EncodeOptions(4, '|'), $encode);
        $decode = new DecodeOptions(4, false);
        $this->assertEquals(new DecodeOptions(1, true, SpecVersion::V4_1), $decode->withVersion(SpecVersion::V4_1)->withIndent(1)->withStrict(true));
        $this->assertEquals(new DecodeOptions(4, false), $decode);
    }

    public function test_version_is_per_call_and_never_inferred_from_content(): void
    {
        $encode = new EncodeOptions(version: SpecVersion::V4_1);
        $decode = new DecodeOptions(version: SpecVersion::V4_1);
        for ($i = 0; $i < 3; $i++) {
            $this->assertSame('#hello', Toon::encode('#hello'));
            $this->assertSame('"#hello"', Toon::encode('#hello', $encode));
            $this->assertSame('#hello', Toon::decode('#hello'));
            $this->assertSame([], Toon::decode('#hello', $decode));
            $this->assertSame('', Toon::encode([]));
            $this->assertSame('[]', Toon::encode([], $encode));
            $this->assertSame('#hello', toon('#hello'));
            $this->assertSame('"#hello"', toon('#hello', $encode));
        }
        $this->assertFalse(Toon::validate("#comment\nk: 1"));
        $this->assertTrue(Toon::validate("#comment\nk: 1", $decode));
        $this->assertTrue(Toon::validate('"\\u0041"', $decode));
        $this->assertSame('A', Toon::decode('"\\u0041"', $decode));
    }

    public function test_empty_and_numeric_key_objects_are_preserved_when_encoding_41(): void
    {
        $options = new EncodeOptions(version: SpecVersion::V4_1);
        $this->assertSame('', Toon::encode(new \stdClass, $options));
        $this->assertSame('empty:', Toon::encode(['empty' => new \stdClass], $options));
        $this->assertSame('"0": value', Toon::encode((object) ['0' => 'value'], $options));
        $this->assertSame('', Toon::encode(new \stdClass));
    }

    public function test_unicode_escape_boundaries_without_host_character_conversion(): void
    {
        foreach ([SpecVersion::V4_1, SpecVersion::V4_4] as $version) {
            $options = new DecodeOptions(version: $version);
            $this->assertSame("\x00\x7F\xC2\x80\xDF\xBF\xE0\xA0\x80\xED\x9F\xBF\xEE\x80\x80\xEF\xBF\xBF", Toon::decode('"\\u0000\\u007f\\u0080\\u07ff\\u0800\\ud7ff\\ue000\\uffff"', $options));
        }
    }
}
