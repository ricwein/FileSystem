<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage\Disk;

use ricwein\FileSystem\Exception\FileAlreadyExistsException;
use ricwein\FileSystem\Helper\Path;
use ricwein\FileSystem\Storage\Disk;

/**
 * like Disk, but for temporary files
 */
class Temp extends Disk
{

    /**
     * try to create random tempfile,
     * retry if file already exists
     * @var int
     */
    protected const MAX_RETRY = 4;

    /**
     * @var bool
     */
    protected $isFreed = false;

    /**
     * @throws FileAlreadyExistsException
     */
    public function __construct()
    {
        for ($try = 0; $try < self::MAX_RETRY; $try++) {
            $this->path = new Path([
                \sys_get_temp_dir(),
                'tmp.' . \bin2hex(\random_bytes(16))
            ]);

            if (!file_exists($this->path->raw)) {
                $this->touch(true);
                return;
            }
        }

        throw new FileAlreadyExistsException('unable to create temp file', 500);
    }

    /**
     * @inheritDoc
     */
    public function remove(): bool
    {
        $this->isFreed = true;
        return parent::remove();
    }

    /**
     * remove tempfile on free
     */
    public function __destruct()
    {
        if (!$this->isFreed) {
            $this->remove();
        }
    }
}
