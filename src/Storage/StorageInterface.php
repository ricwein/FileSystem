<?php

namespace ricwein\FileSystem\Storage;

use ricwein\FileSystem\Path;

interface StorageInterface
{
    /**
     * returns all detail-information for testing/debugging purposes
     * @internal
     */
    public function getDetails(): array;

    /**
     * @internal
     */
    public function getPath(): Path;

    /**
     * check if file exists and is an actual file
     * @internal
     */
    public function isFile(): bool;

    /**
     * check if path is directory
     * @internal
     */
    public function isDir(): bool;

    /**
     * check if path is a symlink
     * @internal
     */
    public function isSymlink(): bool;

    /**
     * check if path is readable
     * @internal
     */
    public function isReadable(): bool;

    /**
     * check if path is writeable
     * @internal
     */
    public function isWriteable(): bool;

    /**
     * @internal
     */
    public function isDotfile(): bool;

    /**
     * Remove file or directory from filesystem on object destruction.
     * @internal
     */
    public function removeOnFree(bool $activate = true): static;


    /**
     * size of file from storage
     * @internal
     */
    public function getSize(): int;
}
