<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Enum;

enum Time: int
{
    case LAST_MODIFIED = 0b0001;
    case LAST_ACCESSED = 0b0010;
    case CREATED = 0b0100;
}
