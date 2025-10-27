<?php

declare(strict_types=1);

namespace HelgeSverre\Toon;

final class Toon
{
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
