<?php

declare(strict_types=1);

namespace HelgeSverre\Toon;

use InvalidArgumentException;

final class DecodeOptions
{
    /**
     * Create new decoding options.
     *
     * @param  int  $indent  Expected number of spaces per indentation level (default: 2)
     * @param  bool  $strict  Enable strict mode validation (default: true)
     *
     * @throws InvalidArgumentException If indent is negative
     */
    public function __construct(
        public readonly int $indent = 2,
        public readonly bool $strict = true,
    ) {
        if ($this->indent < 0) {
            throw new InvalidArgumentException('Indent must be non-negative');
        }
    }

    /**
     * Create options with default values.
     *
     * @return self Default options (indent: 2, strict: true)
     */
    public static function default(): self
    {
        return new self;
    }

    /**
     * Create options with lenient validation.
     *
     * Disables strict mode for more forgiving parsing:
     * - Allows count mismatches
     * - Allows irregular indentation (floor division)
     * - Allows blank lines in arrays
     * - Allows tabs in indentation
     * - Allows empty input
     *
     * Ideal for hand-written TOON or exploratory parsing.
     *
     * @return self Lenient options (indent: 2, strict: false)
     */
    public static function lenient(): self
    {
        return new self(
            indent: 2,
            strict: false
        );
    }

    /**
     * Create a copy with different indentation.
     *
     * @param  int  $indent  Expected number of spaces per indentation level
     * @return self New instance with updated indent
     */
    public function withIndent(int $indent): self
    {
        return new self($indent, $this->strict);
    }

    /**
     * Create a copy with different strict mode setting.
     *
     * @param  bool  $strict  Enable/disable strict mode validation
     * @return self New instance with updated strict setting
     */
    public function withStrict(bool $strict): self
    {
        return new self($this->indent, $strict);
    }
}
