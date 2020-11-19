<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Enum;

class Time
{
    public const LAST_MODIFIED = 0b0001;
    public const LAST_ACCESSED = 0b0010;
    public const CREATED = 0b0100;
}
