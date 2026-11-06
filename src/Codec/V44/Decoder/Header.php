<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Codec\V44\Decoder;

/**
 * A parsed array header such as users[2]{id,name}: or prices[3|]: 1|2|3.
 */
final class Header
{
    /**
     * @param  list<Field>|null  $fields
     */
    public function __construct(
        public readonly ?string $key,
        public readonly int $length,
        public readonly bool $keyed,
        public readonly string $delimiter,
        public readonly ?array $fields,
        public readonly string $inline,
    ) {}
}
