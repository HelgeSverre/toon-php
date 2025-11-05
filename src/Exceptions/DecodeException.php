<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Exceptions;

use RuntimeException;

/**
 * Base exception for all TOON decoding errors.
 *
 * All decoder exceptions extend this base class, allowing callers to catch
 * any decoding error with a single catch block.
 */
class DecodeException extends RuntimeException
{
    /**
     * Create a new decode exception with context.
     *
     * @param  string  $message  Error description
     * @param  int  $toonLine  Line number where error occurred (1-indexed)
     * @param  string|null  $snippet  Content of the problematic line
     */
    public function __construct(
        string $message,
        protected readonly int $toonLine = 0,
        protected readonly ?string $snippet = null,
    ) {
        $fullMessage = $message;

        if ($this->toonLine > 0) {
            $fullMessage = "Line {$this->toonLine}: {$message}";
        }

        if ($this->snippet !== null) {
            $fullMessage .= "\n  > {$this->snippet}";
        }

        parent::__construct($fullMessage);
    }

    /**
     * Get the line number where the error occurred.
     *
     * @return int Line number (1-indexed), or 0 if not applicable
     */
    public function getToonLine(): int
    {
        return $this->toonLine;
    }

    /**
     * Get the content of the line where the error occurred.
     *
     * @return string|null Line content, or null if not available
     */
    public function getSnippet(): ?string
    {
        return $this->snippet;
    }
}
