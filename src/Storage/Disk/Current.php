<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage\Disk;

use ricwein\FileSystem\Helper\Path;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Storage\Disk;
use ricwein\FileSystem\Storage\Storage;

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
        $this->path = new Path(array_merge([getcwd()], $path));
    }

    /**
     * @inheritDoc
     */
    public function setConstraints(int $constraints): Storage
    {
        return parent::setConstraints($constraints & ~Constraint::IN_SAFEPATH);
    }
}
