<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage;

/**
 * base-implementation for all Storage Adapters
 */
abstract class Storage
{
    /**
     * check if file exists and is an actual file
     * @return bool
     */
    abstract public function isFile(): bool;

    /**
     * check if path is directory
     * @return bool
     */
    abstract public function isDirectory():bool;

    /**
     * check if file exists and is executable
     * @return bool
     */
    abstract public function isExecutable(): bool;

    /**
     * check if path is a symlink
     * @return bool
     */
    abstract public function isSymlink(): bool;

    /**
     * check if path is readable
     * @return bool
     */
    abstract public function isReadable(): bool;

    /**
     * check if path is writeable
     * @return bool
     */
    abstract public function isWriteable(): bool;

    /**
     * @return string
     */
    abstract public function read(): string;

    /**
     * @param  bool $ifNewOnly
     * @return bool
     */
    abstract public function touch(bool $ifNewOnly = false): bool;
}
