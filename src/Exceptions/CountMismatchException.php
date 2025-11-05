<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Exceptions;

/**
 * Exception thrown when array/row counts don't match declared values in strict mode.
 *
 * Examples:
 * - Inline array has 3 values but header says [5]
 * - Tabular array has 2 rows but header says [3]
 * - Tabular row has 4 values but field list has 3 fields
 */
class CountMismatchException extends StrictModeException
{
    /**
     * Create a count mismatch exception.
     *
     * @param  string  $message  Error description
     * @param  int  $expected  Expected count
     * @param  int  $actual  Actual count
     * @param  int  $line  Line number where error occurred
     * @param  string|null  $snippet  Content of the problematic line
     */
    public function __construct(
        string $message,
        protected readonly int $expected,
        protected readonly int $actual,
        int $line = 0,
        ?string $snippet = null,
    ) {
        parent::__construct($message, $line, $snippet);
    }

    /**
     * Get the expected count.
     *
     * @return int Expected count
     */
    public function getExpected(): int
    {
        return $this->expected;
    }

    /**
     * Get the actual count.
     *
     * @return int Actual count
     */
    public function getActual(): int
    {
        return $this->actual;
    }
}
