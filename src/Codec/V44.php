<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Codec;

use HelgeSverre\Toon\Constants;
use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\LineWriter;

/** @internal The opt-in 4.4 wire-format implementation. */
final class V44 implements Codec
{
    public function encode(mixed $value, EncodeOptions $options): string
    {
        $normalized = V44\Normalize::normalizeValue($value);
        $writer = new LineWriter(str_repeat(Constants::SPACE, $options->indent));
        (new V44\Encoders($options, $writer))->encodeValue($normalized);

        return $writer->toString();
    }

    public function decode(string $document, DecodeOptions $options): mixed
    {
        return (new V44\Decoder\Parser($options))->parse(V44\Decoder\Tokenizer::tokenize($document, $options));
    }
}
