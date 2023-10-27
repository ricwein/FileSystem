<?php

/**
 * @author Richard Weinhold
 */

declare(strict_types=1);

namespace ricwein\FileSystem\Storage\Extensions\Binary;

use ricwein\FileSystem\Storage\Disk as DiskStorage;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Storage\Extensions\Binary;

class Disk extends Binary
{
    protected ?string $filePath;

    /** @var resource|null */
    protected $handle;
    private array $stat;

    /**
     * @inheritDoc
     * @throws RuntimeException
     */
    public function __construct(int $mode, DiskStorage $storage)
    {
        parent::__construct($mode);

        $this->filePath = (false !== $realpath = $storage->getPath()->getRealPath()) ? $realpath : null;
        $this->openHandle($mode);
    }

    /**
     * close file-handle on free
     */
    public function __destruct()
    {
        if ($this->handle === null) {
            return;
        }

        flock($this->handle, LOCK_UN);
        fclose($this->handle);
        clearstatcache(false, $this->filePath);
    }


    /**
     * @throws RuntimeException
     */
    protected function openHandle(int $mode): void
    {
        if ($this->handle !== null) {
            return;
        }

        $handle = @fopen($this->filePath, ($mode === static::MODE_READ) ? 'rb' : 'wb');

        if ($handle === false) {
            $this->handle = null;
            $this->mode = static::MODE_CLOSED;
            throw new RuntimeException('unable to open file-handle', 500);
        }

        $this->handle = $handle;

        if (!flock($this->handle, LOCK_NB | (($mode === self::MODE_READ) ? LOCK_SH : LOCK_EX))) {
            throw new RuntimeException('unable to get file-lock', 500);
        }

        $this->stat = fstat($this->handle);
        $this->pos = 0;
    }

    /**
     * @inheritDoc
     * @throws AccessDeniedException
     * @throws RuntimeException
     */
    public function write(string $bytes, ?int $length = null): int
    {
        $this->applyAccessMode(static::MODE_WRITE);

        if (false === $bytesCount = mb_strlen($bytes, '8bit')) {
            throw new RuntimeException('invalid byte-count', 500);
        }

        if (($length === null) || ($length > $bytesCount)) {
            $length = (int)$bytesCount;
        } elseif ($length < 0) {
            throw new RuntimeException('invalid byte-count', 500);
        }

        $remaining = $length;

        do {
            if ($remaining <= 0) {
                break;
            }

            $written = fwrite($this->handle, $bytes, $remaining);
            if ($written === false) {
                throw new RuntimeException('Could not write to the file', 500);
            }

            $bytes = mb_substr($bytes, $written, null, '8bit');
            $this->pos += $written;
            $this->stat = fstat($this->handle);
            $remaining -= $written;
        } while ($remaining > 0);

        return $length;
    }

    /**
     * @inheritDoc
     * @throws AccessDeniedException
     */
    public function read(int $length): string
    {
        $this->applyAccessMode(static::MODE_READ);

        if ($length < 0) {
            throw new RuntimeException('invalid byte-count', 500);
        }

        if ($length === 0) {
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
            $read = fread($this->handle, $remaining);

            if (!is_string($read)) {
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
    public function getSize(): int
    {
        return $this->stat['size'];
    }

    /**
     * @inheritDoc
     * @throws RuntimeException
     */
    public function seek(int $position = 0): bool
    {
        $this->pos = $position;

        if ($this->handle === null) {
            throw new RuntimeException('no file-handle found', 500);
        }

        if (fseek($this->handle, $position) !== 0) {
            throw new RuntimeException('fseek() failed', 500);
        }

        return true;
    }

    /**
     * runtime test to prevent TOCTOU attacks (race conditions) through
     * verifying that the hash matches and the current cursor position/file
     * size matches their values when the file was first opened
     * @throws RuntimeException
     */
    protected function toctouTest(): void
    {
        if ($this->handle === null) {
            throw new RuntimeException('no file-handle found', 500);
        }

        if (ftell($this->handle) !== $this->pos) {
            throw new RuntimeException('Read-only file has been modified since it was opened for reading', 500);
        }

        $stat = fstat($this->handle);
        if ($stat['size'] !== $this->stat['size']) {
            throw new RuntimeException('Read-only file has been modified since it was opened for reading', 500);
        }
    }
}
