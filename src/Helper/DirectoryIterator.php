<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Helper;

use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\File;

/**
 * provides filterable directory list support
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
     * @param bool    $recursive
     */
    public function __construct(Storage $storage, bool $recursive = false)
    {
        $this->storage = $storage;
        $this->recursive = $recursive;
    }

    /**
     * @param  callable $filter in format: function(Storage $file): bool
     * @return self
     */
    public function filterStorage(callable $filter): self
    {
        $this->storageFilters[] = $filter;
        return $this;
    }

    /**
     * @param  callable $filter in format: function(FileSystem $file): bool
     * @return self
     */
    public function filter(callable $filter): self
    {
        $this->filesystemFilters[] = $filter;
        return $this;
    }

    /**
     * @param int|null $constraints
     * @return File[]|Directory[]
     */
    public function all(?int $constraints = null): \Generator
    {
        /** @var Storage $file */
        foreach ($this->storage->list($this->recursive) as $storage) {

            // apply early lowlevel filters
            foreach ($this->storageFilters as $filter) {
                if (!call_user_func($filter, $storage)) {
                    continue 2; // continue outer storage-loop
                }
            }

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
     * @return File[]
     */
    public function files(?int $constraints = null): \Generator
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
     * @return Directory[]
     */
    public function dirs(?int $constraints = null): \Generator
    {
        foreach ($this->all($constraints) as $directory) {
            if ($directory->isDir()) {
                yield $directory;
            }
        }
    }
}
