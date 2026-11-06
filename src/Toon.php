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
     * @param  EncodeOptions|null  $options  Optional encoding options (delimiter, indent, spec version)
     * @return string The TOON-formatted string
     */
    public static function encode(mixed $value, ?EncodeOptions $options = null): string
    {
        // Resolve options
        $options ??= EncodeOptions::default();

        return self::codec($options->version)->encode($value, $options);
    }

    /**
     * Decode a TOON string to a PHP value.
     *
     * Parses TOON format back into PHP data structures (arrays, primitives).
     * Objects are decoded as associative arrays.
     *
     * @param  string  $toon  The TOON-formatted string to decode
     * @param  DecodeOptions|null  $options  Optional decoding options (indent, strict mode, spec version)
     * @return mixed The decoded PHP value
     *
     * @throws Exceptions\DecodeException If decoding fails
     */
    public static function decode(string $toon, ?DecodeOptions $options = null): mixed
    {
        // Resolve options
        $options ??= DecodeOptions::default();

        return self::codec($options->version)->decode($toon, $options);
    }

    /**
     * Validate TOON input.
     *
     * Returns whether the document would decode successfully for the given
     * options. This runs the full decoder, guaranteeing that validate() and
     * decode() always agree: validate() is true exactly when decode() does not
     * throw.
     *
     * @param  string  $toon  The TOON-formatted string to validate
     * @param  DecodeOptions|null  $options  Optional decoding options (indent, strict mode, spec version)
     * @return bool True when the document is valid for the given options, false otherwise
     */
    public static function validate(string $toon, ?DecodeOptions $options = null): bool
    {
        try {
            self::decode($toon, $options);

            return true;
        } catch (Exceptions\DecodeException) {
            return false;
        }
    }

    private static function codec(SpecVersion $version): Codec\Codec
    {
        return match ($version) {
            SpecVersion::V3_3 => new Codec\V33,
            SpecVersion::V4_1 => new Codec\V41,
            SpecVersion::V4_4 => new Codec\V44,
        };
    }

    private function __construct()
    {
        // Prevent instantiation
    }
}
