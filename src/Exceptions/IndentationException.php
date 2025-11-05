<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Exceptions;

/**
 * Exception thrown when indentation validation fails in strict mode.
 *
 * Examples:
 * - Leading spaces not a multiple of indentSize
 * - Tabs used in indentation (strict mode)
 */
class IndentationException extends StrictModeException
{
    //
}
