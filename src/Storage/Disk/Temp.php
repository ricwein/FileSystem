<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage\Disk;

use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Storage\Storage;
use ricwein\FileSystem\Exceptions\FileAlreadyExistsException;
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
    public function setConstraints(int $constraints): Storage
    {
        return parent::setConstraints($constraints & ~Constraint::IN_OPENBASEDIR);
    }

    /**
     * @inheritDoc
     */
    public function removeFile(): bool
    {
        $this->isFreed = true;
        return parent::removeFile();
    }

    /**
     * remove tempfile on free
     */
    public function __destruct()
    {
        if (!$this->isFreed) {
            $this->removeFile();
        }
    }
}
