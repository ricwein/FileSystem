<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem;

use ricwein\FileSystem\Helper\Hash;
use ricwein\FileSystem\Helper\Path;
use ricwein\FileSystem\Storage\Disk;
use ricwein\FileSystem\Storage\Storage;
use ricwein\FileSystem\Exception\RuntimeException;

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
     */
    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
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
     * @return self
     */
    abstract public function remove(): self;

    /**
     * calculate size
     * @return int
     */
    abstract public function getSize(): int;

    /**
     * calculate hash above content or filename
     * @param int $mode Hash::CONTENT | Hash::FILENAME | Hash::FILEPATH
     * @param string $algo hashing-algorigthm
     * @return string
     */
    abstract public function getHash(int $mode = Hash::CONTENT, string $algo = 'sha256'): string;
}
