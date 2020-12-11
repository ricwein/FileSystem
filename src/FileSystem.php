<?php

/**
 * @author Richard Weinhold
 */

declare(strict_types=1);

namespace ricwein\FileSystem;

use DateTime;
use Exception;
use ricwein\FileSystem\Enum\Hash;
use ricwein\FileSystem\Enum\Time;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Helper\Path;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Exceptions\RuntimeException;

/**
 * base of all FileSystem type-classes (File/Directory)
 */
abstract class FileSystem
{
    /**
     * @var Storage
     */
    protected Storage $storage;

    /**
     * @param Storage $storage
     * @param int $constraints Constraint::LOOSE || Constraint::STRICT || Constraint::IN_SAFEPATH | Constraint::IN_OPENBASEDIR | Constraint::DISALLOW_LINK
     */
    public function __construct(Storage $storage, int $constraints = Constraint::STRICT)
    {
        $this->storage = $storage;
        $this->storage->setConstraints($constraints);
    }

    /**
     * validate constraints and check file permissions
     * @return void
     * @throws Exceptions\ConstraintsException
     */
    protected function checkFileReadPermissions(): void
    {
        if (!$this->storage->doesSatisfyConstraints()) {
            throw $this->storage->getConstraintViolations();
        }
    }

    /**
     * free internal resources
     */
    public function __destruct()
    {
        unset($this->storage);
    }

    /**
     * @return Storage
     * @internal this should only be used for debugging purposes
     */
    public function storage(): Storage
    {
        return $this->storage;
    }

    /**
     * @return Path
     * @throws RuntimeException
     */
    public function path(): Path
    {
        if ($this->storage instanceof Storage\Disk) {
            return $this->storage->path();
        }

        throw new RuntimeException('unable to fetch path from non-disk FileSystem', 500);
    }

    /**
     * @return bool
     */
    public function isDotfile(): bool
    {
        return $this->storage->isDotfile();
    }

    /**
     * get last-modified timestamp
     * @param int $type
     * @return int|null
     * @throws Exceptions\ConstraintsException
     */
    public function getTime(int $type = Time::LAST_MODIFIED): ?int
    {
        $this->checkFileReadPermissions();
        return $this->storage->getTime($type);
    }

    /**
     * get last-modified as DateTime
     * @param int $type
     * @return DateTime|null
     * @throws Exception
     */
    public function getDate(int $type = Time::LAST_MODIFIED): ?DateTime
    {
        $timestamp = $this->getTime($type);
        return DateTime::createFromFormat('U', (string)$timestamp);
    }

    /**
     * remove file
     * @return self
     */
    abstract public function remove(): self;

    /**
     * check if path is readable
     * @return bool
     */
    public function isReadable(): bool
    {
        return $this->storage->isReadable();
    }

    /**
     * check if path is writeable
     * @return bool
     */
    public function isWriteable(): bool
    {
        return $this->storage->isWriteable();
    }

    /**
     * check if path is a symlink
     * @return bool
     */
    public function isSymlink(): bool
    {
        return $this->storage->isSymlink();
    }

    /**
     * calculate hash above content or filename
     * @param int $mode Hash::CONTENT | Hash::FILENAME | Hash::FILEPATH
     * @param string $algo hashing-algorithm
     * @param bool $raw
     * @return string
     */
    abstract public function getHash(int $mode = Hash::CONTENT, string $algo = 'sha256', bool $raw = false): string;

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->storage->doesSatisfyConstraints();
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return (string)$this->storage;
    }

    /**
     * check if file exists and is an actual file
     * @return bool
     */
    public function isFile(): bool
    {
        return false;
    }

    /**
     * check if directory exists and is an actual directory
     * @return bool
     */
    public function isDir(): bool
    {
        return false;
    }

    /**
     * remove file from filesystem on object destruction
     * => leaving scope or removing object reference
     * @param bool $activate
     * @return self
     * @throws AccessDeniedException
     */
    public function removeOnFree(bool $activate = true): self
    {
        if (!$this->storage->doesSatisfyConstraints()) {
            throw new AccessDeniedException(sprintf('unable to remove: "%s"', $this->storage instanceof Storage\Disk ? $this->storage->path()->raw : get_class($this->storage)), 404, $this->storage->getConstraintViolations());
        }
        $this->storage->removeOnFree($activate);
        return $this;
    }

    /**
     * cast current object to given class-name,
     * reusing internal storage-objects
     * @param string $class
     * @return self
     */
    public function as(string $class): self
    {
        return new $class($this->storage);
    }
}
