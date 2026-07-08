<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\Exceptions\DecodeException;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidationParityTest extends TestCase
{
    #[DataProvider('strictValidDocuments')]
    public function test_validate_matches_decode_for_strict_valid_documents(string $toon, ?DecodeOptions $options = null): void
    {
        $this->assertTrue($this->decodeSucceeds($toon, $options));
        $this->assertTrue(Toon::validate($toon, $options));
    }

    #[DataProvider('strictInvalidDocuments')]
    public function test_validate_matches_decode_for_strict_invalid_documents(string $toon, ?DecodeOptions $options = null): void
    {
        $this->assertFalse($this->decodeSucceeds($toon, $options));
        $this->assertFalse(Toon::validate($toon, $options));
    }

    #[DataProvider('lenientDocuments')]
    public function test_validate_matches_decode_for_lenient_documents(string $toon): void
    {
        $options = DecodeOptions::lenient();

        $this->assertSame(
            $this->decodeSucceeds($toon, $options),
            Toon::validate($toon, $options)
        );
    }

    /**
     * @return iterable<string, array{0: string, 1?: DecodeOptions}>
     */
    public static function strictValidDocuments(): iterable
    {
        yield 'unquoted primitive' => ['hello'];
        yield 'quoted primitive' => ['"hello world"'];
        yield 'integer primitive' => ['42'];
        yield 'scientific notation' => ['1.5E6'];
        yield 'leading zero string' => ['0001'];
        yield 'escaped string' => ['"say \\"hello\\""'];
        yield 'simple object' => ["id: 123\nname: Alice"];
        yield 'mixed object' => ["id: 1\nactive: true\nprice: 9.99\nstatus: null"];
        yield 'nested object' => ["user:\n  id: 123\n  name: Alice"];
        yield 'custom indent object' => ["outer:\n    inner: value", new DecodeOptions(indent: 4)];
        yield 'object with trailing newline' => ["id: 123\n"];
        yield 'object with blank lines outside arrays' => ["id: 1\n\nname: Alice\n\nemail: alice@test.com"];
        yield 'inline array' => ['[3]: a,b,c'];
        yield 'inline array mixed' => ['[5]: 42,true,false,null,hello'];
        yield 'inline array quoted strings' => ['[3]: "hello world","foo,bar",test'];
        yield 'inline array in object' => ["nums: [3]: 1,2,3\nname: test"];
        yield 'list array' => ["[3]:\n  - apple\n  - banana\n  - cherry"];
        yield 'list array nested objects' => ["[2]:\n  - id: 1\n    name: Alice\n  - id: 2\n    name: Bob"];
        yield 'list array in object' => ["items:\n  [2]:\n    - apple\n    - banana\ncount: 2"];
        yield 'tabular array' => ["[2]{id,name}:\n  1,Alice\n  2,Bob"];
        yield 'tabular array without length' => ["{id,name}:\n  1,Alice\n  2,Bob\n  3,Charlie"];
        yield 'tabular array in object' => ["users:\n  [2]{id,name}:\n    1,Alice\n    2,Bob\ntotal: 2"];
        yield 'tabular array quoted fields' => ["[2]{\"first name\",\"last name\"}:\n  Alice,Smith\n  Bob,Jones"];
        yield 'tabular array null values' => ["[2]{id,name,email}:\n  1,Alice,null\n  2,Bob,null"];
        yield 'tab in quoted string' => ["text: \"hello\tworld\""];
        yield 'tab in values' => ["[2]: a\tb,c\td"];
        yield 'blank line after array before sibling' => ["nums:\n  [2]:\n    - 1\n    - 2\n\nname: test"];
        yield 'blank line after tabular before sibling' => ["users:\n  [2]{id,name}:\n    1,Alice\n    2,Bob\n\ncount: 2"];
    }

    /**
     * @return iterable<string, array{0: string, 1?: DecodeOptions}>
     */
    public static function strictInvalidDocuments(): iterable
    {
        yield 'invalid escape x' => ['"\\x41"'];
        yield 'invalid escape u' => ['"\\u0041"'];
        yield 'unterminated string' => ['"hello'];
        yield 'missing colon in object' => ["id: 1\nname Alice"];
        yield 'missing colon after key' => ["id 123\nname: Alice"];
        yield 'empty input' => [''];
        yield 'whitespace only input' => ["   \n  \n   "];
        yield 'tab indentation' => ["\tid: 1"];
        yield 'indent not multiple' => ["key:\n   value: test"];
        yield 'custom indent mismatch' => ["key:\n  value: test", new DecodeOptions(indent: 4)];
        yield 'inline array count mismatch few' => ['[3]: a,b'];
        yield 'inline array count mismatch many' => ['[2]: a,b,c,d'];
        yield 'list array count mismatch' => ["[3]:\n  - a\n  - b"];
        yield 'list array missing hyphen' => ["[2]:\n  - a\n  b"];
        yield 'tabular row width mismatch' => ["[1]{id,name,email}:\n  1,Alice"];
        yield 'tabular count mismatch' => ["[3]{id,name}:\n  1,Alice\n  2,Bob"];
        yield 'blank line before list items' => ["[3]:\n\n  - a\n  - b\n  - c"];
        yield 'blank line in list array' => ["[3]:\n  - a\n\n  - b\n  - c"];
        yield 'blank line before tabular rows' => ["[2]{id,name}:\n\n  1,Alice\n  2,Bob"];
        yield 'blank line in tabular rows' => ["[3]{id,name}:\n  1,Alice\n\n  2,Bob\n  3,Charlie"];
        yield 'nested blank line in array' => ["data:\n  [2]:\n    - a\n\n    - b"];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function lenientDocuments(): iterable
    {
        yield 'lenient empty input' => [''];
        yield 'lenient whitespace only input' => ["  \n  "];
        yield 'lenient inline count mismatch few' => ['[5]: a,b,c'];
        yield 'lenient inline count mismatch many' => ['[2]: a,b,c,d'];
        yield 'lenient list count mismatch' => ["[4]:\n  - a\n  - b"];
        yield 'lenient list count mismatch many' => ["[2]:\n  - a\n  - b\n  - c"];
        yield 'lenient tabular row count mismatch' => ["[3]{id,name}:\n  1,Alice\n  2,Bob"];
        yield 'lenient blank line list array' => ["[3]:\n  - a\n\n  - b\n  - c"];
        yield 'lenient blank line tabular array' => ["[2]{id,name}:\n  1,Alice\n\n  2,Bob"];
        yield 'lenient irregular indentation' => ["key:\n   value: test"];
        yield 'lenient tabs in indentation' => ["\tid: 1"];
        yield 'lenient mixed edge case' => ["id: 1\n name: test\n  [5]: a,b,c"];
        yield 'lenient invalid escape still fails' => ['"test\\xAB"'];
        yield 'lenient tabular width mismatch still fails' => ["[1]{id,name,email}:\n  1,Alice"];
    }

    private function decodeSucceeds(string $toon, ?DecodeOptions $options = null): bool
    {
        try {
            Toon::decode($toon, $options);

            return true;
        } catch (DecodeException) {
            return false;
        }
    }
}
