<?php

declare(strict_types=1);

namespace HelgeSverre\Toon;

final class Constants
{
    // List Markers
    public const LIST_ITEM_MARKER = '-';

    public const LIST_ITEM_PREFIX = '- ';

    // Structural Characters
    public const COMMA = ',';

    public const COLON = ':';

    public const SPACE = ' ';

    public const PIPE = '|';

    // Brackets and Braces
    public const OPEN_BRACKET = '[';

    public const CLOSE_BRACKET = ']';

    public const OPEN_BRACE = '{';

    public const CLOSE_BRACE = '}';

    // Literals
    public const NULL_LITERAL = 'null';

    public const TRUE_LITERAL = 'true';

    public const FALSE_LITERAL = 'false';

    // Escape Characters
    public const BACKSLASH = '\\';

    public const DOUBLE_QUOTE = '"';

    public const NEWLINE = "\n";

    public const CARRIAGE_RETURN = "\r";

    public const TAB = "\t";

    // Delimiters
    public const DELIMITER_COMMA = ',';

    public const DELIMITER_TAB = "\t";

    public const DELIMITER_PIPE = '|';

    public const DEFAULT_DELIMITER = self::DELIMITER_COMMA;

    private function __construct()
    {
        // Prevent instantiation
    }
}
