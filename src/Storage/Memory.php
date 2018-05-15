<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage;

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
    public function touch(bool $ifNewOnly = false): Storage
    {
        return $this;
    }
}
