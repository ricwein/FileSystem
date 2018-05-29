<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage\Extensions;

use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Exceptions\RuntimeException;

/**
 * allows binary file access
 */
abstract class Binary
{
    /**
     * @var Storage
     */
    protected $storage;

    /**
     * @var int
     */
    protected $pos = 0;

    /**
     * @param Storage $storage
     */
    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Write to a stream
     * @param  string $buf bytes
     * @param  int|null $num byte-count
     * @return int
     */
    abstract public function writeBytes(string $buf, ?int $num = null): int;

    /**
     * read from a stream
     * prevent partial reads (also uses run-time testing to prevent partial reads)
     * @param  int $num byte-count
     * @return string
     * @throws RuntimeException
     */
    abstract public function readBytes(int $num): string;

    /**
     * get number of bytes remaining
     * @return int
     */
    abstract public function remainingBytes(): int;

    /**
     * current position in file-buffer
     * @return int
     */
    public function getPos(): int
    {
        return $this->pos;
    }

    /**
     * set the current cursor position to the desired location
     * @param  int $position
     * @return bool
     */
    abstract public function reset(int $position = 0): bool;
}
