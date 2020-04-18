<?php

/**
 * @author Richard Weinhold
 */

namespace ricwein\FileSystem\Helper;

use Generator;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\Exception;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\File;
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
    protected array $storageFilters = [];

    /**
     * @var callable[]
     */
    protected array $filesystemFilters = [];

    /**
     * @var callable|null
     */
    protected $pathFilter = null;

    /**
     * @var Storage
     */
    protected Storage $storage;

    /**
     * @var bool
     */
    protected bool $recursive;

    /**
     * @param Storage $storage
     * @param bool $recursive
     */
    public function __construct(Storage $storage, bool $recursive = false)
    {
        $this->storage = $storage;
        $this->recursive = $recursive;
    }

    /**
     * @param callable $filter in format: function(Storage $file): bool
     * @return self
     */
    public function filterStorage(callable $filter): self
    {
        $this->storageFilters[] = $filter;
        return $this;
    }

    /**
     * @param callable $filter in format: function(FileSystem $file): bool
     * @return self
     */
    public function filter(callable $filter): self
    {
        $this->filesystemFilters[] = $filter;
        return $this;
    }

    /**
     * fastest, but most low-level filter
     * @param callable $filter in format: function(SplFileInfo $file, mixed $key): bool
     * @return $this
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
     * @return Generator
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     * @throws RuntimeException
     */
    public function storages(): Generator
    {
        /** @var Generator $iterator */
        if ($this->pathFilter === null) {
            $iterator = $this->storage->list($this->recursive);
        } elseif ($this->storage instanceof Storage\Disk) {
            $iterator = $this->storage->list($this->recursive, $this->pathFilter);
        } else {
            throw new RuntimeException(sprintf('Found Unsupported Storage Engine (%s) for pathFilter.', get_class($this->storage)), 400);
        }

        /** @var Storage $storage */
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
     * @param int|null $constraints
     * @return Generator
     * @throws UnsupportedException
     * @throws AccessDeniedException
     * @throws Exception
     * @throws UnexpectedValueException
     */
    public function all(?int $constraints = null): Generator
    {
        /** @var Storage $storage */
        foreach ($this->storages() as $storage) {
            $constraints = $constraints ?? $this->storage->getConstraints();
            $file = $storage->isDir() ? new Directory($storage, $constraints) : new File($storage, $constraints);

            // apply late highlevel filters
            foreach ($this->filesystemFilters as $filter) {
                if (!call_user_func($filter, $file)) {
                    continue 2; // continue outer storage-loop
                }
            }

            yield $file;
        }
    }

    /**
     * list only files
     * @param int|null $constraints
     * @return Generator
     * @throws AccessDeniedException
     * @throws Exception
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
     * @param int|null $constraints
     * @return Generator
     * @throws AccessDeniedException
     * @throws Exception
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
}
