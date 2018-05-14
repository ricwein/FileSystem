<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage;

use ricwein\FileSystem\Exception\FileAlreadyExistsException;
use ricwein\FileSystem\Storage\Disk\Path;

/**
 * like Disk, but for temporary files
 */
class Temp extends Disk
{

    /**
     * @var int
     */
    const MAX_RETRY = 4;

    /**
     * @throws FileAlreadyExistsException
     */
    public function __construct()
    {
        for ($try = 0; $try < self::MAX_RETRY; $try++) {
            $this->path = new Path([
                sys_get_temp_dir(),
                uniqid('tmpfile.')
            ]);

            if (!file_exists($this->path->raw)) {
                $this->touch(true);
                return;
            }
        }

        throw new FileAlreadyExistsException('unable to create temp file', 500);
    }

    /**
     * remove tempfile on free
     */
    public function __destruct()
    {
        if ($this->path->real !== null) {
            unlink($this->path->real);
        }
    }
}
