<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage\Disk;

use ricwein\FileSystem\Helper\Path;
use ricwein\FileSystem\Storage\Disk;
use ricwein\FileSystem\FileSystem;

/**
 * creates a absolute path from current-working-directory
 */
class Current extends Disk
{

    /**
     * @param string|FileSystem|Path $path ,...
     */
    public function __construct(... $path)
    {
        $this->path = new Path(array_merge([getcwd()], $path));
    }
}
