<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem;

use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Helper\Hash;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;

/**
 * represents a selected directory
 */
class Directory extends FileSystem
{
    /**
     * @inheritDoc
     */
    public function __construct(Storage\Storage $storage, int $constraints = Constraint::STRICT)
    {
        if ($storage instanceof Storage\Memory) {
            throw new UnexpectedValueException('in-memory directories are not supported', 500);
        }

        if ($storage instanceof Storage\Disk\Temp && !$storage->createDir()) {
            throw new AccessDeniedException('unable to create temp file', 500);
        }

        parent::__construct($storage, $constraints);
    }

    /**
     * create new dir if not exists
     * @return self
     */
    public function create():self
    {
        if (!$this->storage->isDir()) {
            $this->storage->mkdir();
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function remove(): FileSystem
    {
        $this->storage->removeDir();
        return $this;
    }

    /**
     * check if directory exists and is an actual directory
     * @return bool
     */
    public function isDir(): bool
    {
        return $this->storage->isDir();
    }

    /**
     * @param bool $recursive
     * @param int|null $constraints
     * @return File[]|Directory[]
     */
    public function list(bool $recursive = false, ?int $constraints = null): \Generator
    {
        /** @var Storage\Disk $file */
        foreach ($this->storage->list($recursive) as $file) {
            if ($file->isDir()) {
                yield new Directory($file, $constraints ?? $this->storage->getConstraints());
            } else {
                yield new File($file, $constraints ?? $this->storage->getConstraints());
            }
        }
    }

    /**
     * @inheritDoc
     * @param bool $recursive
     */
    public function getHash(int $mode = Hash::CONTENT, string $algo = 'sha256', bool $recursive = true): string
    {
        $fileHashes = [];

        /** @var Directory|File $entry */
        foreach ($this->list($recursive) as $entry) {
            $fileHashes[] = $entry->getHash($mode, $algo);
        }

        return hash($algo, implode(':', $fileHashes), false);
    }
}
