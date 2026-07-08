<?php

declare(strict_types=1);

namespace HelgeSverre\Toon;

use InvalidArgumentException;

final class EncodeOptions
{
    /**
     * Create new encoding options.
     *
     * @param  int  $indent  Number of spaces per indentation level (default: 2, minimum: 1)
     * @param  string  $delimiter  Field delimiter: comma ',', tab '\t', or pipe '|' (default: comma)
     *
     * @throws InvalidArgumentException If indent is less than 1 or delimiter is invalid
     */
    public function __construct(
        public readonly int $indent = 2,
        public readonly string $delimiter = Constants::DEFAULT_DELIMITER,
    ) {
        // §12: indentation is a fixed number of spaces per level; depth is only
        // recoverable when indentSize >= 1, so indent 0 cannot represent nesting.
        if ($this->indent < 1) {
            throw new InvalidArgumentException('Indent must be a positive integer (at least 1)');
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
     * Uses minimal (single-space) indentation and comma delimiter for smallest
     * output size while still preserving nesting so output round-trips.
     *
     * @return self Compact options (indent: 1, delimiter: comma)
     */
    public static function compact(): self
    {
        return new self(
            indent: 1,
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
