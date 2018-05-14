<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem;

use ricwein\FileSystem\Storage\Storage;

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
}
