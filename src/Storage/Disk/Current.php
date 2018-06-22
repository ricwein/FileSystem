<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage\Disk;

use ricwein\FileSystem\Helper\Path;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Storage\Disk;
use ricwein\FileSystem\Storage;

/**
 * creates a absolute path from current-working-directory
 */
class Current extends Disk
{

    /**
     * @inheritDoc
     */
    public function __construct(... $path)
    {
        array_unshift($path, getcwd());
        $this->path = new Path($path);
    }

    /**
     * @inheritDoc
     */
    public function setConstraints(int $constraints): Storage
    {
        return parent::setConstraints($constraints & ~Constraint::IN_SAFEPATH);
    }
}
