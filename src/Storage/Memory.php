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
    protected $content;

    /**
     * @param string $content
     */
    public function __construct(string $content)
    {
        $this->content = $content;
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


    public function read(): string
    {
        return $this->content;
    }
}
