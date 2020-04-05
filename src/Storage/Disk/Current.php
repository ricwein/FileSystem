<?php

/**
 * @author Richard Weinhold
 */

namespace ricwein\FileSystem\Storage\Disk;

use ricwein\FileSystem\FileSystem;
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
    public function __construct(...$path)
    {
        $fistComponent = reset($path);

        // check if our first (left) pathcomponent references to root (/),
        // only inject current working directory if this is not the case
        if (
            empty($path) || (is_string($fistComponent) && strpos($fistComponent, DIRECTORY_SEPARATOR) !== 0) || ($fistComponent instanceof Path && strpos($fistComponent->raw, DIRECTORY_SEPARATOR) !== 0) || ($fistComponent instanceof FileSystem && strpos($fistComponent->path()->raw, DIRECTORY_SEPARATOR) !== 0)
        ) {
            array_unshift($path, getcwd());
        }

        parent::__construct(...$path);
    }

    /**
     * @inheritDoc
     */
    public function setConstraints(int $constraints): Storage
    {
        return parent::setConstraints($constraints & ~Constraint::IN_SAFEPATH);
    }
}
