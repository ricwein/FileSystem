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
     * @var DiskStorage
     */
    protected $storage;

    /**
     * @var resource|null
     */
    protected $handle = null;

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
        if ($this->handle === null) {
            return;
        }

        \flock($this->handle, LOCK_UN);
        \fclose($this->handle);
        \clearstatcache($this->storage->path()->real);
    }


    /**
     * @param  int $mode
     * @return void
     * @throws RuntimeException
     */
    protected function openHandle(int $mode)
    {
        $this->applyAccessMode($mode);

        if ($this->handle !== null) {
            return;
        }

        $this->handle = @\fopen($this->storage->path()->real, ($mode === static::MODE_READ) ? 'rb' : 'wb');
        if ($this->handle === false) {
            $this->handle = null;
            $this->mode = static::MODE_CLOSED;

            throw new RuntimeException('unable to open file-handle', 500);
        }

        if (!\flock($this->handle, LOCK_NB | (($mode === self::MODE_READ) ? LOCK_SH : LOCK_EX))) {
            throw new RuntimeException('unable to get file-lock', 500);
        }

        $this->stat = \fstat($this->handle);
        $this->pos  = 0;
    }

    /**
     * @inheritDoc
     */
    public function write(string $bytes, ?int $length = null): int
    {
        $this->openHandle(static::MODE_WRITE);

        $bytesCount = mb_strlen($bytes, '8bit');

        if ($length === null || $length > $bytesCount) {
            $length = $bytesCount;
        } elseif ($length < 0) {
            throw new RuntimeException('invalid byte-count', 500);
        }

        $remaining = $length;

        do {
            if ($remaining <= 0) {
                break;
            }

            $written = \fwrite($this->handle, $bytes, $remaining);
            if ($written === false) {
                throw new RuntimeException('Could not write to the file', 500);
            }

            $bytes = \mb_substr($bytes, $written, null, '8bit');
            $this->pos += $written;
            $this->stat = \fstat($this->handle);
            $remaining -= $written;
        } while ($remaining > 0);

        return $length;
    }

    /**
     * @inheritDoc
     */
    public function read(int $length): string
    {
        $this->openHandle(static::MODE_READ);

        if ($length < 0) {
            throw new RuntimeException('invalid byte-count', 500);
        } elseif ($length === 0) {
            return '';
        }

        if (($this->pos + $length) > $this->stat['size']) {
            throw new RuntimeException('Out-of-bounds read', 500);
        }

        $retVal = '';
        $remaining = $length;

        $this->toctouTest();

        do {
            if ($remaining <= 0) {
                break;
            }

            /** @var string $read */
            $read = \fread($this->handle, $remaining);

            if (!\is_string($read)) {
                throw new AccessDeniedException('reading file failed', 500);
            }

            $retVal .= $read;
            $readSize = mb_strlen($read, '8bit');
            $this->pos += $readSize;
            $remaining -= $readSize;
        } while ($remaining > 0);

        return $retVal;
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
    public function seek(int $position = 0): bool
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
