<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests\Spec;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\Exceptions\DecodeException;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

/**
 * Production-path coverage for array-length and tabular field-list parsing.
 *
 * These rules were previously exercised only through internal helper methods
 * (`DelimiterParser::extractLength` / `extractFields`) that no production code
 * consumed. The helpers were removed as dead code; the behaviour they enforced
 * is a real requirement of the decoder, so it is asserted here against the
 * public `Toon::decode()` path (which parses headers inline in `HeaderParser`).
 */
final class MalformedHeaderDecodeTest extends TestCase
{
    /**
     * @dataProvider malformedHeaders
     */
    public function test_strict_decode_rejects_malformed_header(string $toon): void
    {
        $this->expectException(DecodeException::class);
        Toon::decode($toon, new DecodeOptions(strict: true));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function malformedHeaders(): iterable
    {
        // Array length section (formerly DelimiterParser::extractLength).
        yield 'negative length' => ['n[-5]: a,b'];
        yield 'negative length -1' => ['n[-1]: a'];
        yield 'missing opening bracket' => ['5]: x'];
        yield 'non-numeric length' => ['n[abc]: x'];
        yield 'leading-zero length' => ['n[03]: a,b,c'];

        // Tabular field list (formerly DelimiterParser::extractFields).
        yield 'missing closing brace' => ["u[1]{id,name:\n  1,2"];
        yield 'empty field list with data' => ["u[1]{}:\n  x"];
    }

    /**
     * Happy path for the field list: quoted names and surrounding whitespace are
     * handled (formerly DelimiterParser::extractFields' passing cases).
     */
    public function test_decode_parses_quoted_and_trimmed_tabular_fields(): void
    {
        $toon = "u[1]{ \"first name\" , last }:\n  Ada,Lovelace";

        $this->assertSame(
            ['u' => [['first name' => 'Ada', 'last' => 'Lovelace']]],
            Toon::decode($toon)
        );
    }

    /**
     * Characterization: an empty field name is accepted as the empty-string key
     * rather than rejected, consistent with the quoted-empty-field form
     * `[1]{""}:` (MutationKillersTest #39). This documents intentional decoder
     * behaviour that differs from the stricter, now-removed helper.
     */
    public function test_decode_accepts_empty_field_name_as_empty_key(): void
    {
        $this->assertSame(
            ['u' => [['id' => 1, '' => 2, 'name' => 3]]],
            Toon::decode("u[1]{id,,name}:\n  1,2,3")
        );
    }
}
