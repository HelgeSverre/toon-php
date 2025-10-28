<?php

declare(strict_types=1);

namespace HelgeSverre\Toon;

use InvalidArgumentException;

final class EncodeOptions
{
    /**
     * Create new encoding options.
     *
     * @param  int  $indent  Number of spaces for indentation (default: 2)
     * @param  string  $delimiter  Field delimiter: comma ',', tab '\t', or pipe '|' (default: comma)
     * @param  string|false  $lengthMarker  Prefix for length markers, '#' or false to disable (default: false)
     *
     * @throws InvalidArgumentException If indent is negative, delimiter is invalid, or lengthMarker is not '#' or false
     */
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

    /**
     * Create options with default values.
     *
     * @return self Default options (indent: 2, delimiter: comma, lengthMarker: false)
     */
    public static function default(): self
    {
        return new self;
    }

    /**
     * Create a copy with different indentation.
     *
     * @param  int  $indent  Number of spaces for indentation
     * @return self New instance with updated indent
     */
    public function withIndent(int $indent): self
    {
        return new self($indent, $this->delimiter, $this->lengthMarker);
    }

    /**
     * Create a copy with different delimiter.
     *
     * @param  string  $delimiter  Field delimiter: comma ',', tab '\t', or pipe '|'
     * @return self New instance with updated delimiter
     */
    public function withDelimiter(string $delimiter): self
    {
        return new self($this->indent, $delimiter, $this->lengthMarker);
    }

    /**
     * Create a copy with different length marker setting.
     *
     * @param  string|false  $lengthMarker  Prefix for length markers, '#' or false to disable
     * @return self New instance with updated length marker
     */
    public function withLengthMarker(string|false $lengthMarker): self
    {
        return new self($this->indent, $this->delimiter, $lengthMarker);
    }
}
