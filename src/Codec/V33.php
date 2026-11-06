<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Codec;

use HelgeSverre\Toon\Constants;
use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\Decoder;
use HelgeSverre\Toon\EncodeOptions;
use HelgeSverre\Toon\Encoders;
use HelgeSverre\Toon\LineWriter;
use HelgeSverre\Toon\Normalize;

/** @internal The established 3.3 implementation, including its public helper classes. */
final class V33 implements Codec
{
    public function encode(mixed $value, EncodeOptions $options): string
    {
        $normalized = Normalize::normalizeValue($value);
        $writer = new LineWriter(str_repeat(Constants::SPACE, $options->indent));
        (new Encoders($options, $writer))->encodeValue($normalized);

        return $writer->toString();
    }

    public function decode(string $document, DecodeOptions $options): mixed
    {
        return (new Decoder\Parser($options))->parse(Decoder\Tokenizer::tokenize($document, $options));
    }
}
