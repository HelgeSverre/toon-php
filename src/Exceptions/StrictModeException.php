<?php

declare(strict_types=1);

namespace HelgeSverre\Toon\Exceptions;

/**
 * Exception thrown when strict mode validation fails.
 *
 * Strict mode enforces:
 * - Array count matches declared [N]
 * - Tabular row width matches field count
 * - Indentation is exact multiple of indentSize
 * - No tabs in indentation
 * - No blank lines in arrays
 * - No empty input
 *
 * Disable strict mode with DecodeOptions::lenient() for more forgiving parsing.
 */
class StrictModeException extends DecodeException
{
    //
}
