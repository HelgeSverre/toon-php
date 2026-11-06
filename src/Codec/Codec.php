<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Codec;

use HelgeSverre\Toon\DecodeOptions;
use HelgeSverre\Toon\EncodeOptions;

/** @internal */
interface Codec
{
    public function encode(mixed $value, EncodeOptions $options): string;

    public function decode(string $document, DecodeOptions $options): mixed;
}
