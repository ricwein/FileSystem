<?php

/**
 * @author Richard Weinhold
 */

declare(strict_types=1);

namespace ricwein\FileSystem\Storage\Disk;

use ricwein\FileSystem\FileSystem;
use ricwein\FileSystem\Helper\Path;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Storage\Disk;

/**
 * creates a absolute path from current-working-directory
 */
class Current extends Disk
{

    /**
     * @inheritDoc
     */
    public function __construct(string|FileSystem|Path|Disk ...$path)
    {
        $fistComponent = reset($path);

        // check if our first (left) path component references to root (/),
        // only inject current working directory if this is not the case
        if (
            empty($path)
            || (is_string($fistComponent) && !str_starts_with($fistComponent, DIRECTORY_SEPARATOR))
            || ($fistComponent instanceof Path && !str_starts_with($fistComponent->raw, DIRECTORY_SEPARATOR))
            || ($fistComponent instanceof FileSystem && !str_starts_with($fistComponent->path()->raw, DIRECTORY_SEPARATOR))
        ) {
            array_unshift($path, getcwd());
        }

        parent::__construct(...$path);
    }

    /**
     * @inheritDoc
     */
    public function setConstraints(int $constraints): static
    {
        return parent::setConstraints($constraints & ~Constraint::IN_SAFEPATH);
    }
}
