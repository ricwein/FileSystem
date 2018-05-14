<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage;

use ricwein\FileSystem\Directory;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Exception\Exception;
use ricwein\FileSystem\Exception\FileNotFoundException;
use ricwein\FileSystem\Storage\Disk\Path;

/**
 * represents a file/directory at the local filesystem
 */
class Disk extends Storage
{
    /**
     * @var Path|null
     */
    protected $path = null;

    /**
     * @param string|Directory|File $path ,...
     */
    public function __construct(... $path)
    {
        $this->path = new Path($path);
    }

    /**
     * @inheritDoc
     */
    public function isFile(): bool
    {
        return $this->path->real !== null && file_exists($this->path->real) && is_file($this->path->real);
    }

    /**
     * @inheritDoc
     */
    public function isDirectory():bool
    {
        return $this->path->real !== null && is_dir($this->path->real);
    }

    /**
     * @inheritDoc
     */
    public function isExecutable(): bool
    {
        return $this->isFile() && is_executable($this->path->real);
    }

    /**
     * @inheritDoc
     */
    public function isSymlink(): bool
    {
        return $this->path->real !== null && is_link($this->path->real);
    }

    /**
     * @inheritDoc
     */
    public function isReadable(): bool
    {
        return $this->path->real !== null && is_readable($this->path->real);
    }

    /**
     * @inheritDoc
     */
    public function isWriteable(): bool
    {
        return $this->path->real !== null && is_writable($this->path->real);
    }

    /**
     * @inheritDoc
     */
    public function read(): string
    {
        if (!$this->isFile()) {
            throw new FileNotFoundException('file not found', 404);
        }
        return file_get_contents($this->path->real);
    }

    /**
     * @param  bool $ifNewOnly
     * @return bool
     */
    public function touch(bool $ifNewOnly = false): bool
    {
        if ($ifNewOnly === true && $this->isFile()) {
            return true;
        }

        // actual touch file
        if (!touch($this->path->raw)) {
            throw new Exception('unable to touch file', 500);
        }

        // reset internal path-state to re-evaluate the realpath
        $this->path->reload();

        return $result;
    }
}
