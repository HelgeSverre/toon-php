<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Codec\V44\Decoder;

/**
 * One field in a table header. A field like customer{name,country} has children.
 */
final class Field
{
    /**
     * @param  list<Field>|null  $children
     */
    public function __construct(
        public readonly string $name,
        public readonly ?array $children = null,
    ) {}
}
