<?php

/**
 * @author Richard Weinhold
 */

namespace ricwein\FileSystem\Storage\Disk;

use Exception;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Helper\Path;
use ricwein\FileSystem\Storage\Disk;

/**
 * like Disk, but for temporary files
 */
class Temp extends Disk
{

    /**
     * @inheritDoc
     */
    protected bool $selfdestruct = true;

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function __construct(...$path)
    {
        $filename = sprintf("fs.%s", bin2hex(random_bytes(16)));
        $tmpdir = sys_get_temp_dir();

        if (empty($path)) {
            $this->path = new Path([$tmpdir, $filename]);
            return;
        }

        array_unshift($path, $tmpdir);
        $this->path = new Path($path);
    }

    /**
     * @inheritDoc
     */
    public function setConstraints(int $constraints): Storage
    {
        return parent::setConstraints($constraints & ~Constraint::IN_OPENBASEDIR & ~Constraint::IN_SAFEPATH & ~Constraint::DISALLOW_LINK);
    }

    /**
     * @inheritDoc
     */
    public function removeFile(): bool
    {
        $this->selfdestruct = false;
        return parent::removeFile();
    }

    /**
     * @inheritDoc
     */
    public function removeDir(): bool
    {
        $this->selfdestruct = false;
        return parent::removeDir();
    }
}
