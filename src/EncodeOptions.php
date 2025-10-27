<?php

declare(strict_types=1);

namespace HelgeSverre\Toon;

use InvalidArgumentException;

final class EncodeOptions
{
    public function __construct(
        public readonly int $indent = 2,
        public readonly string $delimiter = Constants::DEFAULT_DELIMITER,
        public readonly string|false $lengthMarker = false,
    ) {
        if ($this->indent < 0) {
            throw new InvalidArgumentException('Indent must be non-negative');
        }

        if (! in_array($this->delimiter, [Constants::DELIMITER_COMMA, Constants::DELIMITER_TAB, Constants::DELIMITER_PIPE], true)) {
            throw new InvalidArgumentException('Invalid delimiter');
        }

        if ($this->lengthMarker !== false && $this->lengthMarker !== '#') {
            throw new InvalidArgumentException('Length marker must be "#" or false');
        }
    }

    public static function default(): self
    {
        return new self;
    }

    public function withIndent(int $indent): self
    {
        return new self($indent, $this->delimiter, $this->lengthMarker);
    }

    public function withDelimiter(string $delimiter): self
    {
        return new self($this->indent, $delimiter, $this->lengthMarker);
    }

    public function withLengthMarker(string|false $lengthMarker): self
    {
        return new self($this->indent, $this->delimiter, $lengthMarker);
    }
}
