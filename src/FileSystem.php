<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem;

use ricwein\FileSystem\Helper\Hash;
use ricwein\FileSystem\Helper\Path;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;

/**
 * base of all FileSystem type-classes (File/Directory)
 */
abstract class FileSystem
{
    /**
     * @var Storage
     */
    protected $storage;

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
     * free internal resources
     */
    public function __destruct()
    {
        $this->storage = null;
    }

    /**
     * @internal this should only be used for debugging purposes
     * @return Storage
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
     * @return int
     */
    public function getTime(): int
    {
        return $this->storage->getTime();
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
     * @return string
     */
    abstract public function getHash(int $mode = Hash::CONTENT, string $algo = 'sha256'): string;

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
        return (string) $this->storage;
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
}
