<?php

declare(strict_types=1);

use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\Toon;

if (! function_exists('toon')) {
    /**
     * Encode a value to TOON format.
     *
     * @param  mixed  $value  The value to encode
     * @param  EncodeOptions|null  $options  Optional encoding options
     * @return string The TOON-encoded string
     */
    function toon(mixed $value, ?EncodeOptions $options = null): string
    {
        return Toon::encode($value, $options);
    }
}

if (! function_exists('toon_compact')) {
    /**
     * Encode a value to TOON format with compact settings.
     *
     * Uses minimal indentation for smallest output size.
     *
     * @param  mixed  $value  The value to encode
     * @return string The TOON-encoded string with compact formatting
     */
    function toon_compact(mixed $value): string
    {
        return Toon::encode($value, EncodeOptions::compact());
    }
}

if (! function_exists('toon_readable')) {
    /**
     * Encode a value to TOON format with readable settings.
     *
     * Uses generous indentation for better human readability.
     *
     * @param  mixed  $value  The value to encode
     * @return string The TOON-encoded string with readable formatting
     */
    function toon_readable(mixed $value): string
    {
        return Toon::encode($value, EncodeOptions::readable());
    }
}

if (! function_exists('toon_tabular')) {
    /**
     * Encode a value to TOON format with tab delimiters.
     *
     * Ideal for data that will be copied to spreadsheet applications.
     *
     * @param  mixed  $value  The value to encode
     * @return string The TOON-encoded string with tab delimiters
     */
    function toon_tabular(mixed $value): string
    {
        return Toon::encode($value, EncodeOptions::tabular());
    }
}

if (! function_exists('toon_compare')) {
    /**
     * Compare TOON and JSON encoding sizes.
     *
     * Returns an array with token/character counts and savings percentage.
     *
     * @param  mixed  $value  The value to compare
     * @param  EncodeOptions|null  $options  Optional TOON encoding options
     * @return array{toon: int, json: int, savings: float, savings_percent: string} Comparison statistics
     */
    function toon_compare(mixed $value, ?EncodeOptions $options = null): array
    {
        $toonOutput = Toon::encode($value, $options);
        $jsonOutput = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($jsonOutput === false) {
            $jsonOutput = '';
        }

        $toonSize = strlen($toonOutput);
        $jsonSize = strlen($jsonOutput);
        $savings = $jsonSize - $toonSize;
        $savingsPercent = $jsonSize > 0 ? ($savings / $jsonSize) * 100 : 0;

        return [
            'toon' => $toonSize,
            'json' => $jsonSize,
            'savings' => $savings,
            'savings_percent' => number_format($savingsPercent, 1).'%',
        ];
    }
}

if (! function_exists('toon_size')) {
    /**
     * Get the character count of TOON-encoded output.
     *
     * Useful for estimating token usage before encoding.
     *
     * @param  mixed  $value  The value to measure
     * @param  EncodeOptions|null  $options  Optional encoding options
     * @return int The character count of the TOON-encoded output
     */
    function toon_size(mixed $value, ?EncodeOptions $options = null): int
    {
        return strlen(Toon::encode($value, $options));
    }
}

if (! function_exists('toon_estimate_tokens')) {
    /**
     * Estimate token count for TOON-encoded output.
     *
     * Uses a simple heuristic: ~4 characters per token (Claude/GPT average).
     * For accurate counts, use the LLM provider's tokenizer.
     *
     * @param  mixed  $value  The value to estimate
     * @param  EncodeOptions|null  $options  Optional encoding options
     * @return int Estimated token count
     */
    function toon_estimate_tokens(mixed $value, ?EncodeOptions $options = null): int
    {
        $size = toon_size($value, $options);

        return (int) ceil($size / 4);
    }
}
