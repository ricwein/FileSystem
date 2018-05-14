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
}
