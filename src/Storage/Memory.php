<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage;

use ricwein\FileSystem\Helper\Hash;
use ricwein\FileSystem\Exceptions\RuntimeException;

/**
 * represents a file/directory from in-memory
 */
class Memory extends Storage
{

    /**
     * @var string
     */
    protected $content = '';

    /**
     * @param string|null $content
     */
    public function __construct(?string $content = null)
    {
        if ($content !== null) {
            $this->content = $content;
        }
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
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isDir():bool
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
        if ($offset !== null && $length !== null) {
            return mb_substr($this->content, $offset, $length, '8bit');
        }

        return $this->content;
    }

    /**
     * @inheritDoc
     */
    public function writeFile(string $content, int $mode = 0): bool
    {
        $this->content = $content;
        return true;
    }

    /**
     * @inheritDoc
     */
    public function removeFile():bool
    {
        $this->content = '';
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getSize(): int
    {
        return mb_strlen($this->content, '8bit');
    }

    /**
     * @inheritDoc
     */
    public function getFileType(bool $withEncoding = false): string
    {
        return (new \finfo($withEncoding ? FILEINFO_MIME : FILEINFO_MIME_TYPE))->buffer($this->content);
    }

    /**
     * @inheritDoc
     */
    public function getFileHash(int $mode = Hash::CONTENT, string $algo = 'sha256'): string
    {
        switch ($mode) {
            case Hash::CONTENT: return hash($algo, $this->content, false);
            case Hash::FILENAME: case Hash::FILEPATH: throw new RuntimeException('unable to calculate filepath/name hash for in-memory-files', 500);
            default: throw new RuntimeException('unknown hashing-mode', 500);
        }
    }

    /**
     * @inheritDoc
     */
    public function getTime(): int
    {
        return time();
    }

    /**
     * @inheritDoc
     */
    public function touch(bool $ifNewOnly = false): bool
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
}
