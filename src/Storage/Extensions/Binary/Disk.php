<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage\Extensions\Binary;

use ricwein\FileSystem\Storage\Disk as DiskStorage;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\AccessDeniedException;

use ricwein\FileSystem\Storage\Extensions\Binary;

/**
 * @inheritDoc
 */
class Disk extends Binary
{
    /**
     * @var int
     */
    private const MODE_CLOSED = 0;

    /**
     * @var int
     */
    private const MODE_READ = 1;

    /**
     * @var int
     */
    private const MODE_WRITE = 2;

    /**
     * @var DiskStorage
     */
    protected $storage;

    /**
     * @var resource|null
     */
    protected $handle = null;

    /**
     * @var int
     */
    protected $mode = self::MODE_CLOSED;

    /**
     * @param DiskStorage $storage
     */
    public function __construct(DiskStorage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * close file-handle on free
     */
    public function __destruct()
    {
        if ($this->handle === null || $this->mode === self::MODE_CLOSED) {
            return;
        }

        @\fclose($this->handle);
        \clearstatcache($this->storage->path()->real);
    }


    /**
     * @param  int $mode
     * @return void
     * @throws RuntimeException
     */
    protected function openHandle(int $mode)
    {
        if ($this->handle !== null) {
            if ($this->mode !== $mode) {
                throw new AccessDeniedException('unable to re-open existing file-handle for another mode', 500);
            }
            return;
        }

        $this->handle = @\fopen($this->storage->path()->real, $mode === self::MODE_READ ? 'rb' : 'wb');
        if ($this->handle === false) {
            $this->handle = null;
            throw new RuntimeException('unable to open file-handle', 500);
        }

        $this->mode = $mode;
        $this->stat = \fstat($this->handle);
        $this->pos  = 0;
    }

    /**
     * @inheritDoc
     */
    public function writeBytes(string $buf, ?int $num = null): int
    {
        $this->openHandle(self::MODE_WRITE);

        $bufSize = mb_strlen($buf, '8bit');
        if ($num === null || $num > $bufSize) {
            $num = $bufSize;
        } elseif ($num < 0) {
            throw new RuntimeException('invalid byte-count', 500);
        }

        $remaining = $num;
        do {
            if ($remaining <= 0) {
                break;
            }

            $written = \fwrite($this->handle, $buf, $remaining);
            if ($written === false) {
                throw new RuntimeException('Could not write to the file', 500);
            }

            $buf = \mb_substr($buf, $written, null, '8bit');
            $this->pos += $written;
            $this->stat = \fstat($this->handle);
            $remaining -= $written;
        } while ($remaining > 0);

        return $num;
    }

    /**
     * @inheritDoc
     */
    public function readBytes(int $num): string
    {
        $this->openHandle(self::MODE_READ);

        if ($num < 0) {
            throw new RuntimeException('invalid byte-count', 500);
        } elseif ($num === 0) {
            return '';
        }

        if (($this->pos + $num) > $this->stat['size']) {
            throw new RuntimeException('Out-of-bounds read', 500);
        }

        $buf       = '';
        $remaining = $num;

        $this->toctouTest();

        do {
            if ($remaining <= 0) {
                break;
            }
            /** @var string $read */
            $read = \fread($this->handle, $remaining);

            if (!\is_string($read)) {
                throw new AccessDeniedException('Could not read from the file', 500);
            }

            $buf .= $read;
            $readSize = mb_strlen($read, '8bit');
            $this->pos += $readSize;
            $remaining -= $readSize;
        } while ($remaining > 0);

        return $buf;
    }

    /**
     * @inheritDoc
     */
    public function remainingBytes(): int
    {
        return (int) (PHP_INT_MAX & ((int) $this->stat['size'] - $this->pos));
    }

    /**
     * @inheritDoc
     */
    public function reset(int $position = 0): bool
    {
        $this->pos = $position;

        if ($this->handle === null) {
            throw new RuntimeException('no file-handle found', 500);
        } elseif (\fseek($this->handle, $position, SEEK_SET) !== 0) {
            throw new RuntimeException('fseek() failed', 500);
        }

        return true;
    }

    /**
     * runtime test to prevent TOCTOU attacks (race conditions) through
     * verifying that the hash matches and the current cursor position/file
     * size matches their values when the file was first opened
     * @throws RuntimeException
     * @return void
     */
    protected function toctouTest()
    {
        if ($this->handle === null) {
            throw new RuntimeException('no file-handle found', 500);
        } elseif (\ftell($this->handle) !== $this->pos) {
            throw new RuntimeException('Read-only file has been modified since it was opened for reading', 500);
        }

        $stat = \fstat($this->handle);
        if ($stat['size'] !== $this->stat['size']) {
            throw new RuntimeException('Read-only file has been modified since it was opened for reading', 500);
        }
    }
}
