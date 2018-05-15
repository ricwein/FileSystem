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
     * returns all detail-informations for testing/debugging purposes
     * @return string[]
     */
    public function getDetails(): array
    {
        return [
            'storage' => static::class,
        ];
    }

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
     * write content to storage
     * @param  string $content
     * @param int $mode FILE_USE_INCLUDE_PATH | FILE_APPEND | LOCK_EX
     * @return bool
     */
    abstract public function write(string $content, int $mode = 0): bool;

    /**
     * remove file from storage
     * @return bool
     */
    abstract public function remove(): bool;

    /**
     * @param  bool $ifNewOnly
     * @return self
     */
    abstract public function touch(bool $ifNewOnly = false): self;
}
