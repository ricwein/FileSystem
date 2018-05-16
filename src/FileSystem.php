<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem;

use ricwein\FileSystem\Helper\Hash;
use ricwein\FileSystem\Helper\Path;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Storage\Disk;
use ricwein\FileSystem\Storage\Storage;
use ricwein\FileSystem\Exception\RuntimeException;
use ricwein\FileSystem\Exception\UnexpectedValueException;

/**
 * base of all FileSystem type-classes (File/Directory)
 */
abstract class FileSystem
{

    /**
     * @var int
     */
    protected $constraints;

    /**
     * @var Storage
     */
    protected $storage;

    /**
     * @param Storage $storage
     * @param int $constraints Constraint::LOOSE || Constraint::STRICT || Constraint::IN_SAVEPATH | Constraint::IN_OPENBASEDIR | Constraint::DISALLOW_LINK
     */
    public function __construct(Storage $storage, int $constraints = Constraint::STRICT)
    {
        $this->storage = $storage;
        $this->constraints = $constraints;
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
        if ($this->storage instanceof Disk) {
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
     * @return self
     */
    abstract public function remove(): self;


    /**
    * calculate size
     * @return int
     * @throws UnexpectedValueException
     */
    public function getSize(): int
    {
        return $this->storage->getSize();
    }

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
     * @param string $algo hashing-algorigthm
     * @return string
     */
    abstract public function getHash(int $mode = Hash::CONTENT, string $algo = 'sha256'): string;
}
