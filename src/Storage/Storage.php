<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage;

use ricwein\FileSystem\Helper\Hash;
use ricwein\FileSystem\Exception\Exception;
use ricwein\FileSystem\Exception\FileNotFoundException;
use ricwein\FileSystem\Exception\RuntimeException;

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
     * @throws FileNotFoundException
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
     * size of file from storage
     * @return int|null
     */
    abstract public function getSize(): ?int;

    /**
     * guess content-type (mime) of storage
     * @param bool $withEncoding
     * @return string|null
     */
    abstract public function getType(bool $withEncoding = false): ?string;

    /**
     * calculate hash
     * @param int $mode Hash::CONTENT | Hash::FILENAME | Hash::FILEPATH
     * @param string $algo hashing-algorigthm
     * @return string|null
     * @throws RuntimeException
     */
    abstract public function getHash(int $mode = Hash::CONTENT, string $algo = 'sha256'): ?string;

    /**
     * @param  bool $ifNewOnly
     * @return self
     * @throws Exception
     */
    abstract public function touch(bool $ifNewOnly = false): self;
}
