<?php

namespace Benchmarks;

/**
 * A single token-counting method — an estimator, a local tokenizer, or a hosted
 * count API — identified by a human-readable label. This class exists only for
 * the benchmark's reporting; it is not part of the toon-php public API.
 */
final class TokenCounter
{
    /** @var callable(string): int */
    private $counter;

    /**
     * @param  callable(string): int  $counter
     */
    public function __construct(
        public readonly string $label,
        callable $counter,
    ) {
        $this->counter = $counter;
    }

    public function count(string $text): int
    {
        return ($this->counter)($text);
    }
}
