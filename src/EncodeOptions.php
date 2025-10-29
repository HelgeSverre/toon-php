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
     * Create options optimized for maximum compactness.
     *
     * Uses minimal indentation and comma delimiter for smallest output size.
     * Ideal for production use where token count is critical.
     *
     * @return self Compact options (indent: 0, delimiter: comma, lengthMarker: false)
     */
    public static function compact(): self
    {
        return new self(
            indent: 0,
            delimiter: Constants::DELIMITER_COMMA,
            lengthMarker: false
        );
    }

    /**
     * Create options optimized for human readability.
     *
     * Uses generous indentation for better visual structure.
     * Ideal for debugging, documentation, or human review.
     *
     * @return self Readable options (indent: 4, delimiter: comma, lengthMarker: false)
     */
    public static function readable(): self
    {
        return new self(
            indent: 4,
            delimiter: Constants::DELIMITER_COMMA,
            lengthMarker: false
        );
    }

    /**
     * Create options optimized for tabular data.
     *
     * Uses tab delimiter for maximum compatibility with spreadsheet applications.
     * Ideal for data that will be copied to Excel, CSV tools, or analyzed in tables.
     *
     * @return self Tabular options (indent: 2, delimiter: tab, lengthMarker: false)
     */
    public static function tabular(): self
    {
        return new self(
            indent: 2,
            delimiter: Constants::DELIMITER_TAB,
            lengthMarker: false
        );
    }

    /**
     * Create options with length markers enabled.
     *
     * Includes '#' prefix on array lengths for easier parsing.
     * Ideal when length information is important for processing.
     *
     * @return self Options with length markers (indent: 2, delimiter: comma, lengthMarker: '#')
     */
    public static function withLengthMarkers(): self
    {
        return new self(
            indent: 2,
            delimiter: Constants::DELIMITER_COMMA,
            lengthMarker: '#'
        );
    }

    /**
     * Create options optimized for pipe-delimited output.
     *
     * Uses pipe delimiter which is rare in natural text, reducing escaping needs.
     * Ideal when data contains lots of commas or tabs.
     *
     * @return self Pipe-delimited options (indent: 2, delimiter: pipe, lengthMarker: false)
     */
    public static function pipeDelimited(): self
    {
        return new self(
            indent: 2,
            delimiter: Constants::DELIMITER_PIPE,
            lengthMarker: false
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
