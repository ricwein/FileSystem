<?php

/**
 * @author Richard Weinhold
 */

namespace ricwein\FileSystem\Storage;

use finfo;
use ricwein\FileSystem\Enum\Time;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\Exception;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\Helper\Stream;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Enum\Hash;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Storage\Extensions\Binary;

/**
 * represents a file/directory from in-memory
 */
class Memory extends Storage
{
    protected ?string $content = '';
    protected int $lastModified = 0;
    protected int $lastAccessed = 0;
    protected int $created = 0;

    /**
     * @param string|null $content
     */
    public function __construct(?string $content = null)
    {
        if ($content !== null) {
            $this->content = $content;
        }

        $now = time();
        $this->lastModified = $now;
        $this->lastAccessed = $now;
        $this->created = $now;
    }

    /**
     * @inheritDoc
     */
    public function doesSatisfyConstraints(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isFile(): bool
    {
        return $this->content !== null;
    }

    /**
     * @inheritDoc
     */
    public function isDir(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isExecutable(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isSymlink(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isReadable(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isWriteable(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function readFile(?int $offset = null, ?int $length = null, int $mode = LOCK_SH): string
    {
        $this->lastAccessed = time();
        if ($offset !== null && $length !== null) {
            return mb_substr($this->content ?? '', $offset, $length, '8bit');
        }

        return $this->content ?? '';
    }

    /**
     * @inheritDoc
     */
    public function readFileAsLines(): array
    {
        $this->lastAccessed = time();
        return explode(PHP_EOL, $this->content);
    }

    /**
     * @inheritDoc
     */
    public function streamFile(?int $offset = null, ?int $length = null, int $mode = LOCK_SH): void
    {
        $this->lastAccessed = time();
        echo $this->readFile($offset, $length, $mode);
    }


    /**
     * @inheritDoc
     */
    public function writeFile(string $content, bool $append = false, int $mode = 0): bool
    {
        $this->lastModified = time();

        if ($this->content === null) {
            $this->content = '';
        }

        if ($append) {
            $this->content .= $content;
        } else {
            $this->content = $content;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function removeFile(): bool
    {
        $this->content = null;
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getSize(): int
    {
        return ($this->content === null) ? 0 : mb_strlen($this->content, '8bit');
    }

    /**
     * @inheritDoc
     */
    public function getFileType(bool $withEncoding = false): string
    {
        return (new finfo($withEncoding ? FILEINFO_MIME : FILEINFO_MIME_TYPE))->buffer($this->content ?? '');
    }

    /**
     * @inheritDoc
     */
    public function getFileHash(int $mode = Hash::CONTENT, string $algo = 'sha256'): string
    {
        switch ($mode) {
            case Hash::CONTENT:
                return hash($algo, $this->content ?? '', false);
            case Hash::LAST_MODIFIED:
                return hash($algo, $this->lastModified, false);
            case Hash::FILENAME:
            case Hash::FILEPATH:
                throw new RuntimeException('unable to calculate filepath/name hash for in-memory-files', 500);
            default:
                throw new RuntimeException('unknown hashing-mode', 500);
        }
    }

    /**
     * @inheritDoc
     */
    public function getTime(int $type = Time::LAST_MODIFIED): ?int
    {
        switch ($type) {
            case Time::LAST_MODIFIED:
                return $this->lastModified;
            case Time::LAST_ACCESSED:
                return $this->lastAccessed;
            case Time::CREATED:
                return $this->created;
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function touch(bool $ifNewOnly = false, ?int $time = null, ?int $atime = null): bool
    {
        $this->lastModified = time();
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isDotfile(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     * @return Binary\Memory
     * @throws AccessDeniedException
     */
    public function getHandle(int $mode): Binary
    {
        return new Binary\Memory($mode, $this);
    }

    /**
     * this stream is <b>READONLY!</b>
     * @inheritDoc
     * @throws RuntimeException
     */
    public function getStream(string $mode = 'rb+')
    {
        if (!in_array($mode, ['r+', 'w', 'w+', 'a+', 'x', 'x+', 'c+'], true)) {
            throw new RuntimeException(sprintf('unable to open stream for memory-storage in non-write mode "%s"', $mode), 500);
        }

        $stream = fopen('php://memory', $mode);

        if ($stream === false) {
            throw new RuntimeException('failed to open stream', 500);
        }

        // pre-fill stream
        if ($this->content !== null) {
            fwrite($stream, $this->content);
            rewind($stream);
        }
        return $stream;
    }

    /**
     * @inheritDoc
     * @throws RuntimeException
     */
    public function writeFromStream($stream): bool
    {
        $streamer = new Stream($stream);
        $this->content = $streamer->read();
        return true;
    }

    /**
     * @inheritDoc
     * @param Storage $destination
     * @return bool
     * @throws FileNotFoundException
     * @throws UnsupportedException
     * @throws Exception
     */
    public function copyFileTo(Storage $destination): bool
    {
        switch (true) {

            case $destination instanceof Disk:
                if (!$destination->writeFile($this->readFile())) {
                    return false;
                }
                $destination->path()->reload();
                return true;

            case $destination instanceof Flysystem:
            case $destination instanceof self:
            default:
                return $destination->writeFile($this->readFile());
        }
    }

    /**
     * @inheritDoc
     * @param Storage $destination
     * @return bool
     * @throws Exception
     * @throws FileNotFoundException
     * @throws UnsupportedException
     */
    public function moveFileTo(Storage $destination): bool
    {
        if (!$this->copyFileTo($destination)) {
            return false;
        }

        return $this->removeFile();
    }
}
