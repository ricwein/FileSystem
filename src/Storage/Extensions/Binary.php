<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage\Extensions;

use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\AccessDeniedException;

/**
 * allows binary file access
 */
abstract class Binary
{

    /**
     * @var int
     */
    protected const MODE_CLOSED = 0;

    /**
     * @var int
     */
    protected const MODE_READ = 1;

    /**
     * @var int
     */
    protected const MODE_WRITE = 2;

    /**
     * @var int
     */
    protected $mode = self::MODE_CLOSED;

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
     * check current access-mode and set new modes
     * @param  int $mode
     * @return void
     * @throws AccessDeniedException
     */
    protected function applyAccessMode(int $mode): void
    {
        if ($this->mode === self::MODE_CLOSED) {

            // set new mode
            $this->mode = $mode;
            return;
        } elseif ($mode === $this->mode) {

            // nothing to do
            return;
        }

        // mode-switching detected
        throw new AccessDeniedException('unable to switch access-mode for existing binary file handle', 500);
    }


    /**
     * Write to a stream
     * @param  string $bytes bytes
     * @param  int|null $length byte-count
     * @return int
     */
    abstract public function write(string $bytes, ?int $length = null): int;

    /**
     * read from a stream
     * prevent partial reads (also uses run-time testing to prevent partial reads)
     * @param  int $length byte-count
     * @return string
     * @throws RuntimeException
     */
    abstract public function read(int $length): string;

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
    abstract public function seek(int $position = 0): bool;
}
