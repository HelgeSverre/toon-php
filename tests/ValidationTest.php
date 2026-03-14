<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Tests;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;

final class ValidationTest extends TestCase
{
    public function test_validate_returns_true_for_valid_primitive(): void
    {
        $this->assertTrue(Toon::validate('"hello"'));
        $this->assertTrue(Toon::validate('42'));
    }

    public function test_validate_returns_true_for_valid_nested_document(): void
    {
        $toon = <<<'TOON'
items[2]:
  - id: 1
    users[2]{id,name}:
      1,Ada
      2,Bob
  - id: 2
    active: true
TOON;

        $this->assertTrue(Toon::validate($toon));
    }

    public function test_validate_returns_false_for_invalid_escape_sequence(): void
    {
        $this->assertFalse(Toon::validate('"bad\\xescape"'));
    }

    public function test_validate_respects_strict_mode_by_default(): void
    {
        $this->assertFalse(Toon::validate('[3]: a,b'));
    }

    public function test_validate_respects_lenient_mode(): void
    {
        $options = DecodeOptions::lenient();

        $this->assertTrue(Toon::validate('[3]: a,b', $options));
    }

    public function test_toon_validate_helper_returns_boolean(): void
    {
        $this->assertTrue(toon_validate('key: value'));
        $this->assertFalse(toon_validate("key:\n   invalid: value"));
    }
}
