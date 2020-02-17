<?php

/**
 * @author Richard Weinhold
 */

namespace ricwein\FileSystem\Helper;

use Generator;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\Exception;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\File;

/**
 * provides filterable directory list support,
 * depends on storage->list() implementations
 */
class DirectoryIterator
{
    /**
     * @var callable[]
     */
    protected $storageFilters = [];

    /**
     * @var callable[]
     */
    protected $filesystemFilters = [];

    /**
     * @var Storage
     */
    protected $storage;

    /**
     * @var bool
     */
    protected $recursive;

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
     * low-level storage iterator
     * @return Generator
     * @throws UnsupportedException
     */
    public function storages(): Generator
    {
        /** @var Storage $storage */
        foreach ($this->storage->list($this->recursive) as $storage) {

            // apply early lowlevel filters
            foreach ($this->storageFilters as $filter) {
                if (!call_user_func($filter, $storage)) {
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
