<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Exceptions;

/**
 * Exception thrown when TOON input contains syntax errors.
 *
 * Examples:
 * - Missing colon after key
 * - Invalid escape sequence
 * - Unterminated quoted string
 * - Malformed array header
 */
class SyntaxException extends DecodeException
{
    //
}
