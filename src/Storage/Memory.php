<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage;

use ricwein\FileSystem\Helper\Hash;
use ricwein\FileSystem\Exception\RuntimeException;

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
    public function isFile(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isDirectory():bool
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
    public function read(): string
    {
        return $this->content;
    }

    /**
     * @inheritDoc
     */
    public function write(string $content, int $mode = 0): bool
    {
        $this->content = $content;
        return true;
    }

    /**
     * @inheritDoc
     */
    public function remove():bool
    {
        $this->content = '';
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getSize(): ?int
    {
        return mb_strlen($this->content, '8bit');
    }

    /**
     * @inheritDoc
     */
    public function getType(bool $withEncoding = false): string
    {
        return (new \finfo($withEncoding ? FILEINFO_MIME : FILEINFO_MIME_TYPE))->buffer($this->content);
    }

    /**
     * @inheritDoc
     */
    public function getHash(int $mode = Hash::CONTENT, string $algo = 'sha256'): string
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
    public function touch(bool $ifNewOnly = false): Storage
    {
        return $this;
    }
}
