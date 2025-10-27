<?php

declare(strict_types=1);

namespace HelgeSverre\Toon;

final class LineWriter
{
    /** @var string[] */
    private array $lines = [];

    public function __construct(
        private readonly string $indentationString,
    ) {}

    public function push(int $depth, string $content): void
    {
        $indent = str_repeat($this->indentationString, $depth);
        $this->lines[] = $indent.$content;
    }

    public function toString(): string
    {
        return implode(Constants::NEWLINE, $this->lines);
    }
}
