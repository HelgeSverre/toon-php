<?php

declare(strict_types=1);

namespace HelgeSverre\Toon;

/** Wire-format contract; independent of the Composer package version. */
enum SpecVersion: string
{
    case V3_3 = '3.3';
    case V4_1 = '4.1';
}
