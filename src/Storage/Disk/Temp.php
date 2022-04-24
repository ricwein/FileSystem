<?php

/**
 * @author Richard Weinhold
 */

declare(strict_types=1);

namespace ricwein\FileSystem\Storage\Disk;

use Exception;
use ricwein\FileSystem\FileSystem;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Path;
use ricwein\FileSystem\Storage\Disk;

/**
 * like Disk, but for temporary files
 */
class Temp extends Disk
{

    /**
     * @inheritDoc
     */
    protected bool $selfDestruct = true;

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function __construct(string|FileSystem|Path|Disk ...$path)
    {
        $filename = sprintf('fs.%s', bin2hex(random_bytes(16)));
        $tmpdir = sys_get_temp_dir();

        if (empty($path)) {
            $this->path = new Path($tmpdir, $filename);
            return;
        }

        array_unshift($path, $tmpdir);
        parent::__construct(...$path);
    }

    /**
     * @inheritDoc
     */
    public function setConstraints(int $constraints): static
    {
        return parent::setConstraints($constraints & ~Constraint::IN_OPEN_BASEDIR & ~Constraint::IN_SAFEPATH & ~Constraint::DISALLOW_LINK);
    }

    /**
     * @inheritDoc
     */
    public function removeFile(): bool
    {
        $this->selfDestruct = false;
        return parent::removeFile();
    }

    /**
     * @inheritDoc
     */
    public function removeDir(): bool
    {
        $this->selfDestruct = false;
        return parent::removeDir();
    }
}
