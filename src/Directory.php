<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem;

use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Storage\Memory;
use ricwein\FileSystem\Storage\Storage;
use ricwein\FileSystem\Exception\UnexpectedValueException;

/**
 * represents a selected directory
 */
class Directory extends FileSystem
{
    /**
     * @inheritDoc
     */
    public function __construct(Storage $storage, int $constraints = Constraint::STRICT)
    {
        if ($storage instanceof Memory) {
            throw new UnexpectedValueException('in-memory directories are not supported', 500);
        }

        parent::__construct($storage, $constraints);
    }
}
