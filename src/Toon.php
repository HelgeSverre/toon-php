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

        // Create encoder and encode the value
        $encoder = new Encoders($options, $writer);
        $encoder->encodeValue($normalizedValue);

        // Return the result
        return $writer->toString();
    }

    /**
     * Decode a TOON string to a PHP value.
     *
     * Parses TOON format back into PHP data structures (arrays, primitives).
     * Objects are decoded as associative arrays.
     *
     * @param  string  $toon  The TOON-formatted string to decode
     * @param  DecodeOptions|null  $options  Optional decoding options (indent, strict mode)
     * @return mixed The decoded PHP value
     *
     * @throws Exceptions\DecodeException If decoding fails
     */
    public static function decode(string $toon, ?DecodeOptions $options = null): mixed
    {
        // Resolve options
        $options ??= DecodeOptions::default();

        // Tokenize input
        $lines = Decoder\Tokenizer::tokenize($toon, $options);

        // Parse tokenized lines
        $parser = new Decoder\Parser($options);

        return $parser->parse($lines);
    }

    private function __construct()
    {
        // Prevent instantiation
    }
}
