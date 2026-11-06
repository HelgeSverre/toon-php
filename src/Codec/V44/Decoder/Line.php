<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Codec\V44\Decoder;

/**
 * One prepared input line: its 1-based number, nesting depth and the text after the indentation.
 */
final class Line
{
    public function __construct(
        public readonly int $number,
        public readonly int $depth,
        public readonly string $content,
        public readonly bool $blank,
    ) {}
}
