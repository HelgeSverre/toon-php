<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use HelgeSverre\Toon\Normalize;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;
use TypeError;

final class LegacyIntegerKeyCompatibilityTest extends TestCase
{
    public function test_associative_array_predicates_accept_integer_keys(): void
    {
        $this->assertTrue(Normalize::isJsonObject([2 => 'x']));
        $this->assertTrue(Normalize::isArrayOfObjects([[2 => 'x']]));
    }

    public function test_default_table_preserves_integer_field_key_failure(): void
    {
        $this->expectException(TypeError::class);
        Toon::encode([[2 => 'x']]);
    }

    public function test_default_mixed_list_preserves_integer_field_key_failure(): void
    {
        $this->expectException(TypeError::class);
        Toon::encode([[2 => 'x'], ['name' => 'y']]);
    }
}
