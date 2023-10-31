<?php

declare(strict_types=1);

namespace ricwein\FileSystem;

use DateTime;
use Exception;
use ricwein\FileSystem\Enum\Hash;
use ricwein\FileSystem\Enum\Time;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Model\FileSize;
use ricwein\FileSystem\Storage\BaseStorage;

/**
 * @author Richard Weinhold
 * base of all FileSystem type-classes (File/Directory)
 */
abstract class FileSystem
{
    protected BaseStorage $storage;

    /**
     * @param int $constraints Constraint::LOOSE || Constraint::STRICT || Constraint::IN_SAFEPATH | Constraint::IN_OPEN_BASEDIR | Constraint::DISALLOW_LINK
     */
    public function __construct(BaseStorage $storage, int $constraints = Constraint::STRICT)
    {
        $this->storage = $storage;
        $this->storage->setConstraints($constraints);
    }

    /**
     * validate constraints and check file permissions
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

    public function storage(): BaseStorage
    {
        return $this->storage;
    }

    /**
     * fetch most specific file/directory path, which is available for the used storage engine
     */
    public function getPath(): Path
    {
        return $this->storage->getPath();
    }

    public function isDotfile(): bool
    {
        return $this->storage->isDotfile();
    }

    /**
     * get last-modified timestamp
     * @throws Exceptions\ConstraintsException
     */
    public function getTime(Time $type = Time::LAST_MODIFIED): ?int
    {
        $this->checkFileReadPermissions();
        return $this->storage->getTime($type);
    }

    /**
     * get last-modified as DateTime
     * @throws Exception
     */
    public function getDate(Time $type = Time::LAST_MODIFIED): ?DateTime
    {
        $timestamp = $this->getTime($type);
        return DateTime::createFromFormat('U', (string)$timestamp);
    }

    /**
     * remove file
     */
    abstract public function remove(): static;

    /**
     * check if path is readable
     */
    public function isReadable(): bool
    {
        return $this->storage->isReadable();
    }

    /**
     * check if path is writeable
     */
    public function isWriteable(): bool
    {
        return $this->storage->isWriteable();
    }

    /**
     * check if path is a symlink
     */
    public function isSymlink(): bool
    {
        return $this->storage->isSymlink();
    }

    /**
     * calculate hash above content or filename
     * @param string $algo hashing-algorithm
     */
    abstract public function getHash(Hash $mode = Hash::CONTENT, string $algo = 'sha256', bool $raw = false): string;

    public function isValid(): bool
    {
        return $this->storage->doesSatisfyConstraints();
    }

    public function __toString(): string
    {
        return (string)$this->storage;
    }

    /**
     * check if file exists and is an actual file
     */
    public function isFile(): bool
    {
        return false;
    }

    /**
     * check if directory exists and is an actual directory
     * @throws
     */
    public function isDir(): bool
    {
        return false;
    }

    /**
     * Get filesize as FileSize object.
     */
    abstract public function getSize(): FileSize;

    /**
     * remove file from filesystem on object destruction
     * => leaving scope or removing object reference
     * @throws AccessDeniedException
     */
    public function removeOnFree(bool $activate = true): static
    {
        if (!$this->storage->doesSatisfyConstraints()) {
            throw new AccessDeniedException(sprintf('unable to remove: "%s"', $this->storage instanceof Storage\Disk ? $this->storage->getPath()->getRawPath() : $this->storage::class), 404, $this->storage->getConstraintViolations());
        }
        $this->storage->removeOnFree($activate);
        return $this;
    }

    /**
     * Cast current object to given class-name,
     * reusing internal storage-objects.
     * @param class-string<FileSystem> $class
     */
    public function as(string $class): static
    {
        return new $class($this->storage, $this->storage->getConstraints());
    }

    abstract public function copyTo(BaseStorage $destination, ?int $constraints = null): static;
}
