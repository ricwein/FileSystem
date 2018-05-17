<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem;

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
     * @inheritDoc
     */
    public function remove(): FileSystem
    {
        $this->storage->removeDir();
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getHash(int $mode = Hash::CONTENT, string $algo = 'sha256'): string
    {
        // @TODO implement directory hashing
        return '';
    }

    /**
     * @param bool $recursive
     * @return File[]
     */
    public function list(bool $recursive = false): \Generator
    {
        foreach ($this->storage->list() as $file) {
            yield new File($file, $this->storage->getConstraints());
        }
    }
}
