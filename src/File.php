<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem;

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
     * check if file exists and is an actual file
     * @return bool
     */
    public function isFile(): bool
    {
        return $this->storage->isFile();
    }
}
