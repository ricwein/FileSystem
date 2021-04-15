<?php

/**
 * @author Richard Weinhold
 */

declare(strict_types=1);

namespace ricwein\FileSystem\Storage;

use finfo;
use ricwein\FileSystem\Enum\Time;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\Exception;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
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
        return false;
    }

    /**
     * @inheritDoc
     */
    public function isExecutable(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function isSymlink(): bool
    {
        return false;
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
    public function isDotfile(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function readFile(int $offset = 0, ?int $length = null, int $mode = LOCK_SH): string
    {
        $this->lastAccessed = time();

        if ($offset === 0 && $length === null) {
            return $this->content ?? '';
        }

        return mb_substr($this->content ?? '', $offset, $length, '8bit');
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
    public function streamFile(int $offset = 0, ?int $length = null, int $mode = LOCK_SH): void
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
    public function getFileHash(int $mode = Hash::CONTENT, string $algo = 'sha256', bool $raw = false): string
    {
        switch ($mode) {
            case Hash::CONTENT:
                return hash($algo, $this->content ?? '', $raw);
            case Hash::LAST_MODIFIED:
                return hash($algo, (string)$this->lastModified, $raw);
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
        $this->lastModified = $time ?? time();

        if ($atime !== null) {
            $this->lastAccessed = $atime;
        }

        return true;
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
     * WARNING: the resulting stream is writeable, but will not be applied back to the actual internal memory content
     * @inheritDoc
     * @throws RuntimeException
     */
    public function getStream(string $mode = 'rb+'): Stream
    {
        $modeType = $mode;

        // binary mode
        $binaryMode = str_contains($modeType, 'b');
        $modeType = str_replace('b', '', $modeType);

        // + suffix (adds write to read mode)
        $readAndWriteMode = str_contains($modeType, '+');
        $modeType = str_replace('+', '', $modeType);

        // actual main mode (read or write)
        $readMode = $readAndWriteMode || $modeType === 'r';
        $writeMode = $readAndWriteMode || in_array($modeType, ['w', 'a', 'x', 'c'], true);
        $readAndWriteMode = $readMode && $writeMode;

        // pointer position
        $pointerAtStart = in_array($modeType, ['r', 'x', 'c'], true); // else at the end

        // start with empty content?
        $resetContent = $modeType === 'w';

        $openMode = null;
        if ($readAndWriteMode || ($readMode && !$resetContent)) {
            $openMode = $binaryMode ? 'rb+' : 'r+';
        } elseif ($writeMode) {
            $openMode = $binaryMode ? 'wb' : 'w';
        } elseif ($readMode) {
            $openMode = $binaryMode ? 'rb' : 'r';
        }

        if ($openMode === null) {
            throw new RuntimeException("invalid open-mode for stream: $mode => $modeType", 500);
        }

        // open new empty memory stream
        $stream = Stream::fromResourceName('php://memory', $openMode);

        // pre-fill stream
        if ($readMode && !$resetContent && $this->content !== null) {
            $stream->write($this->content);
            if ($pointerAtStart) {
                $stream->rewind();
            }
        }

        return $stream;
    }

    /**
     * @inheritDoc
     * @throws RuntimeException
     */
    public function writeFromStream(Stream $stream): bool
    {
        $this->content = $stream->read();
        return true;
    }

    /**
     * @inheritDoc
     * @param Storage $destination
     * @return bool
     * @throws Exception
     * @throws FileNotFoundException
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
     */
    public function moveFileTo(Storage $destination): bool
    {
        if (!$this->copyFileTo($destination)) {
            return false;
        }

        return $this->removeFile();
    }
}
