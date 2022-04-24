<?php

/**
 * @author Richard Weinhold
 */

declare(strict_types=1);

namespace ricwein\FileSystem\Enum;

/**
 * enum like hash-type const
 */
enum Hash
{
    /**
     * hash over filename only
     */
    case FILENAME;

    /**
     * hash over full filepath
     */
    case FILEPATH;

    /**
     * hash over file-content
     */
    case CONTENT;

    /**
     * hash over last-modified timestamp, fine to check for changes
     */
    case LAST_MODIFIED;

    public function asString(): string
    {
        return match ($this) {
            self::FILENAME => 'filename',
            self::FILEPATH => 'filepath',
            self::CONTENT => 'content',
            self::LAST_MODIFIED => 'last-modified',
        };
    }
}
