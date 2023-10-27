<?php

namespace ricwein\FileSystem\Storage;

use Generator;

interface DirectoryStorageInterface extends StorageInterface
{
    /**
     * @return Generator<static> list of all files
     * @internal
     */
    public function list(bool $recursive = false, ?int $constraints = null): Generator;

    /**
     * create new directory
     * @internal
     */
    public function mkdir(bool $ifNewOnly = false, int $permissions = 0755): bool;

    /**
     * Removes directory recursively including containing files.
     * @internal
     */
    public function removeDir(): bool;

    /**
     * @internal
     */
    public function cd(array $path): void;
}
