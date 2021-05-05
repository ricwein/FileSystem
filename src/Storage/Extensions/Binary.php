<?php

/**
 * @author Richard Weinhold
 */

declare(strict_types=1);

namespace ricwein\FileSystem\Storage\Extensions;

use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\AccessDeniedException;

/**
 * allows binary file access
 */
abstract class Binary
{
    public const MODE_CLOSED = 0;
    public const MODE_READ = 1;
    public const MODE_WRITE = 2;

    protected int $mode = self::MODE_CLOSED;
    protected int $pos = 0;

    /**
     * @throws AccessDeniedException
     * @internal
     */
    public function __construct(int $mode)
    {
        $this->applyAccessMode($mode);
    }

    /**
     * check current access-mode and set new modes
     * @throws AccessDeniedException
     */
    protected function applyAccessMode(int $mode): void
    {
        if ($this->mode === self::MODE_CLOSED) {

            // set new mode
            $this->mode = $mode;
            return;
        }

        if ($mode === $this->mode) {

            // nothing to do
            return;
        }

        // mode-switching detected
        throw new AccessDeniedException('unable to switch access-mode for existing binary file handle', 500);
    }


    /**
     * Write to a stream
     */
    abstract public function write(string $bytes, ?int $length = null): int;

    /**
     * read from a stream
     * prevent partial reads (also uses run-time testing to prevent partial reads)
     * @throws RuntimeException
     */
    abstract public function read(int $length): string;

    /**
     * get number of bytes remaining
     */
    public function remainingBytes(): int
    {
        return (int)(PHP_INT_MAX & ($this->getSize() - $this->pos));
    }

    /**
     * current position in file-buffer
     */
    public function getPos(): int
    {
        return $this->pos;
    }

    /**
     * gets file-size in bytes
     */
    abstract public function getSize(): int;

    /**
     * set the current cursor position to the desired location
     */
    abstract public function seek(int $position = 0): bool;
}
