<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem;

use ricwein\FileSystem\Storage\Disk;
use ricwein\FileSystem\Exception\AccessDeniedException;

/**
 * represents a selected directory
 */
class File extends FileSystem
{
    /**
     * @return string
     */
    public function read(): string
    {
        return $this->storage->read();
    }

    /**
    * write content to storage
    * @param  string $content
    * @param int $mode FILE_USE_INCLUDE_PATH | FILE_APPEND | LOCK_EX
    * @return self
    * @throws AccessDeniedException
     */
    public function write(string $content, int $mode = 0): self
    {
        if (!$this->storage->write($content, $mode)) {
            throw new AccessDeniedException('unable to wirte file-content', 403);
        }

        return $this;
    }

    /**
     * check if file exists and is an actual file
     * @return bool
     */
    public function isFile(): bool
    {
        return $this->storage->isFile();
    }

    /**
     * @return bool
     */
    public function isDotfile(): bool
    {
        if ($this->storage instanceof Disk) {
            return strpos($this->storage->path()->basename, '.') ===0;
        }
        return false;
    }
}
