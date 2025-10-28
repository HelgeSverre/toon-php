<?php

declare(strict_types=1);

namespace HelgeSverre\Toon;

final class Toon
{
    /**
     * Encode a PHP value to TOON format.
     *
     * Converts PHP data structures (arrays, objects, primitives) into a compact,
     * human-readable Token-Oriented Object Notation format optimized for LLM contexts.
     *
     * @param  mixed  $value  The value to encode (arrays, objects, strings, numbers, booleans, null)
     * @param  EncodeOptions|null  $options  Optional encoding options (delimiter, indent, length marker)
     * @return string The TOON-formatted string
     */
    public static function encode(mixed $value, ?EncodeOptions $options = null): string
    {
        // Resolve options
        $options ??= EncodeOptions::default();

        // Normalize the value
        $normalizedValue = Normalize::normalizeValue($value);

        // Create line writer
        $indentString = str_repeat(Constants::SPACE, $options->indent);
        $writer = new LineWriter($indentString);

        // Encode the value
        Encoders::encodeValue($normalizedValue, $writer, $options);

        // Return the result
        return $writer->toString();
    }

    private function __construct()
    {
        // Prevent instantiation
    }
}
