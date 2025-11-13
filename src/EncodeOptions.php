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
     *
     * @throws InvalidArgumentException If indent is negative or delimiter is invalid
     */
    public function __construct(
        public readonly int $indent = 2,
        public readonly string $delimiter = Constants::DEFAULT_DELIMITER,
    ) {
        if ($this->indent < 0) {
            throw new InvalidArgumentException('Indent must be non-negative');
        }

        if (! in_array($this->delimiter, [Constants::DELIMITER_COMMA, Constants::DELIMITER_TAB, Constants::DELIMITER_PIPE], true)) {
            throw new InvalidArgumentException('Invalid delimiter');
        }
    }

    /**
     * Create options with default values.
     *
     * @return self Default options (indent: 2, delimiter: comma)
     */
    public static function default(): self
    {
        return new self;
    }

    /**
     * Create options optimized for maximum compactness.
     *
     * Uses minimal indentation and comma delimiter for smallest output size.
     * Ideal for production use where token count is critical.
     *
     * @return self Compact options (indent: 0, delimiter: comma)
     */
    public static function compact(): self
    {
        return new self(
            indent: 0,
            delimiter: Constants::DELIMITER_COMMA,
        );
    }

    /**
     * Create options optimized for human readability.
     *
     * Uses generous indentation for better visual structure.
     * Ideal for debugging, documentation, or human review.
     *
     * @return self Readable options (indent: 4, delimiter: comma)
     */
    public static function readable(): self
    {
        return new self(
            indent: 4,
            delimiter: Constants::DELIMITER_COMMA,
        );
    }

    /**
     * Create options optimized for tabular data.
     *
     * Uses tab delimiter for maximum compatibility with spreadsheet applications.
     * Ideal for data that will be copied to Excel, CSV tools, or analyzed in tables.
     *
     * @return self Tabular options (indent: 2, delimiter: tab)
     */
    public static function tabular(): self
    {
        return new self(
            indent: 2,
            delimiter: Constants::DELIMITER_TAB,
        );
    }

    /**
     * Create options optimized for pipe-delimited output.
     *
     * Uses pipe delimiter which is rare in natural text, reducing escaping needs.
     * Ideal when data contains lots of commas or tabs.
     *
     * @return self Pipe-delimited options (indent: 2, delimiter: pipe)
     */
    public static function pipeDelimited(): self
    {
        return new self(
            indent: 2,
            delimiter: Constants::DELIMITER_PIPE,
        );
    }

    /**
     * Create a copy with different indentation.
     *
     * @param  int  $indent  Number of spaces for indentation
     * @return self New instance with updated indent
     */
    public function withIndent(int $indent): self
    {
        return new self($indent, $this->delimiter);
    }

    /**
     * Create a copy with different delimiter.
     *
     * @param  string  $delimiter  Field delimiter: comma ',', tab '\t', or pipe '|'
     * @return self New instance with updated delimiter
     */
    public function withDelimiter(string $delimiter): self
    {
        return new self($this->indent, $delimiter);
    }
}
