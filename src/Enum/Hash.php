<?php

/**
 * @author Richard Weinhold
 */

declare(strict_types=1);

namespace ricwein\FileSystem\Enum;

/**
 * enum like hash-type const
 */
class Hash
{
    /**
     * hash over filename only
     * @var int
     */
    public const FILENAME = 0b0001;

    /**
     * hash over full filepath
     * @var int
     */
    public const FILEPATH = 0b0010;

    /**
     * hash over file-content
     * @var int
     */
    public const CONTENT = 0b0100;

    /**
     * hash over last-modified timestamp, fine to check for changes
     * @var int
     */
    public const LAST_MODIFIED = 0b1000;
}
