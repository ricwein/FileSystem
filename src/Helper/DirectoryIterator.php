<?php
declare(strict_types=1);

namespace ricwein\FileSystem\Helper;

use Generator;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\FileSystem;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Storage\BaseStorage;
use SplFileInfo;

/**
 * provides filterable directory list support,
 * depends on storage->list() implementations
 */
class DirectoryIterator
{
    /**
     * @var callable[]
     */
    private array $storageFilters = [];

    /**
     * @var callable[]
     */
    private array $filesystemFilters = [];

    /**
     * @var callable|null
     */
    private $pathFilter = null;

    public function __construct(
        private readonly BaseStorage&Storage\DirectoryStorageInterface $storage,
        private readonly bool $recursive = false,
        private readonly ?int $constraints = null
    ) {}

    /**
     * @param callable $filter in format: function(Storage $file): bool
     */
    public function filterStorage(callable $filter): self
    {
        $this->storageFilters[] = $filter;
        return $this;
    }

    /**
     * @param callable $filter in format: function(FileSystem $file): bool
     */
    public function filter(callable $filter): self
    {
        $this->filesystemFilters[] = $filter;
        return $this;
    }

    /**
     * fastest, but most low-level filter
     * @param callable $filter in format: function(SplFileInfo $file, mixed $key): bool
     */
    public function filterPath(callable $filter): self
    {
        if ($this->pathFilter === null) {
            $this->pathFilter = $filter;
            return $this;
        }

        $this->pathFilter = function (SplFileInfo $file, $key) use ($filter): bool {
            return call_user_func($this->pathFilter, $file, $key) && $filter($file, $key);
        };

        return $this;
    }

    /**
     * low-level storage iterator
     * @return Generator<BaseStorage>
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     * @throws RuntimeException
     */
    public function storages(): Generator
    {
        /** @var Generator $iterator */
        if ($this->pathFilter === null) {
            $iterator = $this->storage->list($this->recursive, $this->constraints);
        } elseif ($this->storage instanceof Storage\Disk) {
            $iterator = $this->storage->list($this->recursive, $this->constraints, $this->pathFilter);
        } else {
            throw new RuntimeException(sprintf('Found Unsupported Storage Engine (%s) for pathFilter.', get_class($this->storage)), 400);
        }

        foreach ($iterator as $storage) {

            // apply middle low level filters
            foreach ($this->storageFilters as $filter) {
                if (!$filter($storage)) {
                    continue 2; // continue outer storage-loop
                }
            }

            yield $storage;
        }
    }

    /**
     * @return Generator<FileSystem>
     * @throws UnsupportedException
     * @throws AccessDeniedException
     * @throws UnexpectedValueException
     */
    public function all(?int $constraints = null): Generator
    {
        $constraints = $constraints ?? $this->storage->getConstraints();

        /** @var BaseStorage $storage */
        foreach ($this->storages() as $storage) {
            $file = $storage->isDir() ? new Directory($storage, $constraints) : new File($storage, $constraints);

            // apply late high level filters
            foreach ($this->filesystemFilters as $filter) {
                if (!$filter($file)) {
                    continue 2; // continue outer storage-loop
                }
            }

            yield $file;
        }
    }

    /**
     * list only files
     * @return Generator<File>
     * @throws AccessDeniedException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function files(?int $constraints = null): Generator
    {
        foreach ($this->all($constraints) as $file) {
            if (!$file->isDir()) {
                yield $file;
            }
        }
    }

    /**
     * list only directories
     * @return Generator<Directory>
     * @throws AccessDeniedException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function dirs(?int $constraints = null): Generator
    {
        foreach ($this->all($constraints) as $directory) {
            if ($directory->isDir()) {
                yield $directory;
            }
        }
    }

    /**
     * @internal
     */
    public function getStorage(): BaseStorage
    {
        return $this->storage;
    }
}
