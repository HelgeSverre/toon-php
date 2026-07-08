<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests\Spec;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\Exceptions\CountMismatchException;
use HelgeSverre\Toon\Exceptions\DecodeException;
use HelgeSverre\Toon\Exceptions\StrictModeException;
use HelgeSverre\Toon\Exceptions\SyntaxException;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

/**
 * Targeted "killer" decode cases derived from an Infection mutation run.
 *
 * Each case pins a specific decoder behaviour that a surviving mutant could
 * have altered without any existing test failing. Every case has been verified
 * against the current decoder implementation; the asserted result is the actual
 * observed behaviour of Toon::decode().
 *
 * Test data uses real PHP values in providers to avoid JSON-escaping ambiguity.
 */
final class MutationKillersTest extends TestCase
{
    /**
     * @dataProvider decodeValueCases
     */
    public function test_decode(string $toon, bool $strict, mixed $expected): void
    {
        $this->assertSame($expected, Toon::decode($toon, new DecodeOptions(strict: $strict)));
    }

    /**
     * @return array<string, array{0: string, 1: bool, 2: mixed}>
     */
    public static function decodeValueCases(): array
    {
        return [
            '#00 strict nums[3]: 1,2,3' => ['nums[3]: 1,2,3', true, ['nums' => [1, 2, 3]]],
            '#01 strict [3]: a,,c' => ['[3]: a,,c', true, ['a', '', 'c']],
            '#05 strict [1]:\\n  -\\n    id: 1\\n    name: Alice' => ["[1]:\n  -\n    id: 1\n    name: Alice", true, [['id' => 1, 'name' => 'Alice']]],
            '#06 strict [1]:\\n  -\\n    [2]: a,b' => ["[1]:\n  -\n    [2]: a,b", true, [['a', 'b']]],
            '#08 strict [1]:\\n  - name: Alice' => ["[1]:\n  - name: Alice", true, [['name' => 'Alice']]],
            '#09 strict [2]:\\n  - [2]: a,b\\n  - [2]: c,d' => ["[2]:\n  - [2]: a,b\n  - [2]: c,d", true, [['a', 'b'], ['c', 'd']]],
            '#11 strict [1]:\\n  - [2]:\\n    - a\\n    - b' => ["[1]:\n  - [2]:\n    - a\n    - b", true, [['a', 'b']]],
            '#13 strict [2]:\\n  - [1]:\\n    - a\\n  - [1]:\\n    - b' => ["[2]:\n  - [1]:\n    - a\n  - [1]:\n    - b", true, [['a'], ['b']]],
            '#15 strict [1]:\\n  - [1]:\\n    - id: 1\\n      name: A' => ["[1]:\n  - [1]:\n    - id: 1\n      name: A", true, [[['id' => 1, 'name' => 'A']]]],
            '#16 strict [1]:\\n  - user:\\n      id: 1\\n      name: A' => ["[1]:\n  - user:\n      id: 1\n      name: A", true, [['user' => ['id' => 1, 'name' => 'A']]]],
            '#17 strict [1]:\\n  - data:\\n      [2]: a,b' => ["[1]:\n  - data:\n      [2]: a,b", true, [['data' => ['a', 'b']]]],
            '#18 strict [1]:\\n  - id: 1\\n    addr:\\n      city: X' => ["[1]:\n  - id: 1\n    addr:\n      city: X", true, [['id' => 1, 'addr' => ['city' => 'X']]]],
            '#19 strict [1]:\\n  - id: 1\\n    nums:\\n      [2]: a,b' => ["[1]:\n  - id: 1\n    nums:\n      [2]: a,b", true, [['id' => 1, 'nums' => ['a', 'b']]]],
            '#20 strict [1]{id,name}:\\n  1,' => ["[1]{id,name}:\n  1,", true, [['id' => 1, 'name' => '']]],
            '#24 strict [2]:\\n  -\\n  - x' => ["[2]:\n  -\n  - x", true, [[], 'x']],
            '#25 strict "my key"[2]: a,b' => ['"my key"[2]: a,b', true, ['my key' => ['a', 'b']]],
            // A quoted key containing a colon must parse as a keyed inline array;
            // the ':' inside the quotes is not the key/value separator (regression).
            '#23 strict "a:b"[2]: x,y' => ['"a:b"[2]: x,y', true, ['a:b' => ['x', 'y']]],
            '#26 lenient x: "\\"' => ['x: "\\"', false, ['x' => '\\']],
            '#27 lenient k: "aAb"' => ['k: "aAb"', false, ['k' => 'aAb']],
            '#28 lenient k: "a\\nb"' => ['k: "a\\nb"', false, ['k' => "a\nb"]],
            '#29 lenient k: "ab"' => ['k: "ab"', false, ['k' => 'ab']],
            '#32 lenient foo : 1' => ['foo : 1', false, ['foo' => 1]],
            '#33 lenient items[3]:1,2,3' => ['items[3]:1,2,3', false, ['items' => [1, 2, 3]]],
            '#34 lenient foo{a,b}: 1,2' => ['foo{a,b}: 1,2', false, ['foo' => [1, 2]]],
            '#35 lenient x: :y' => ['x: :y', false, ['x' => ':y']],
            '#36 lenient foo [3]: 1,2,3' => ['foo [3]: 1,2,3', false, ['foo' => [1, 2, 3]]],
            '#39 lenient [1]{""}:\\n  x' => ["[1]{\"\"}:\n  x", false, [['' => 'x']]],
            '#40 lenient [1]{x"y"}:\\n  v' => ["[1]{x\"y\"}:\n  v", false, [['x"y"' => 'v']]],
            '#43 lenient  [2]: a,b' => [' [2]: a,b', false, ['a', 'b']],
            '#45 lenient "a\\"b": 1' => ['"a\\"b": 1', false, ['a"b' => 1]],
            '#46 lenient a:\\n  b: 1\\nc: 2\\n  d: 3' => ["a:\n  b: 1\nc: 2\n  d: 3", false, ['a' => ['b' => 1], 'c' => ['d' => 3]]],
            '#47 lenient items[2]: a,b\\n  x: 1' => ["items[2]: a,b\n  x: 1", false, ['items' => ['x' => 1]]],
            '#48 strict a:hello' => ['a:hello', true, ['a' => 'hello']],
            '#50 strict data[1]:\\n  - a\\nmore[1]:\\n  - b' => ["data[1]:\n  - a\nmore[1]:\n  - b", true, ['data' => ['a'], 'more' => ['b']]],
            '#51 strict [1]:\\n  -abc' => ["[1]:\n  -abc", true, ['abc']],
            '#52 strict users[1]:\\n  - id: 1\\n    name: Bob' => ["users[1]:\n  - id: 1\n    name: Bob", true, ['users' => [['id' => 1, 'name' => 'Bob']]]],
            '#53 strict t1[1]{a}:\\n  1\\nt2[1]{a}:\\n  2' => ["t1[1]{a}:\n  1\nt2[1]{a}:\n  2", true, ['t1' => [['a' => 1]], 't2' => [['a' => 2]]]],
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
            '#02 strict x:\\n  "a:b"' => ["x:\n  \"a:b\"", true, SyntaxException::class],
            '#03 strict foo\\nbar' => ["foo\nbar", true, SyntaxException::class],
            '#04 strict [abc]:' => ['[abc]:', true, DecodeException::class],
            '#10 strict [1]:\\n  - [2]:' => ["[1]:\n  - [2]:", true, CountMismatchException::class],
            '#12 strict [1]:\\n  - [2]:\\n      - a\\n      - b' => ["[1]:\n  - [2]:\n      - a\n      - b", true, DecodeException::class],
            '#14 strict [1]:\\n  - [abc]:\\n    x' => ["[1]:\n  - [abc]:\n    x", true, DecodeException::class],
            '#21 strict [1]{id,name}:\\n      1,A' => ["[1]{id,name}:\n      1,A", true, DecodeException::class],
            '#22 strict [1]:\\n      - a' => ["[1]:\n      - a", true, DecodeException::class],
            '#30 lenient k: "\\uD800"' => ['k: "\\uD800"', false, SyntaxException::class],
            '#31 lenient k: "\\uDFFF"' => ['k: "\\uDFFF"', false, SyntaxException::class],
            '#37 lenient [3;: a,b,c' => ['[3;: a,b,c', false, DecodeException::class],
            '#38 lenient []x' => ['[]x', false, DecodeException::class],
            '#41 lenient [' => ['[', false, DecodeException::class],
            '#42 lenient [3' => ['[3', false, DecodeException::class],
            '#44 lenient "hello"\\nworld' => ["\"hello\"\nworld", false, SyntaxException::class],
            '#49 strict a]: x' => ['a]: x', true, SyntaxException::class],
            '#54 strict [3]{a}:\\n  1\\n  2\\n\\n  3' => ["[3]{a}:\n  1\n  2\n\n  3", true, StrictModeException::class],
        ];
    }
}
