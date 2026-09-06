<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Codec;

use HelgeSverre\Toon\Constants;
use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\LineWriter;

/** @internal The opt-in 4.1 wire-format implementation. */
final class V41 implements Codec
{
    public function encode(mixed $value, EncodeOptions $options): string
    {
        $normalized = V41\Normalize::normalizeValue($value);
        $writer = new LineWriter(str_repeat(Constants::SPACE, $options->indent));
        (new V41\Encoders($options, $writer))->encodeValue($normalized);

        return $writer->toString();
    }

    public function decode(string $document, DecodeOptions $options): mixed
    {
        return (new V41\Decoder\Parser($options))->parse(V41\Decoder\Tokenizer::tokenize($document, $options));
    }
}
