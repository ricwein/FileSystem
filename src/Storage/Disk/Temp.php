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
    protected $isFreed = true;

    /**
     * @throws FileAlreadyExistsException
     */
    public function __construct()
    {
        // do nothing
    }

    /**
     * @return bool
     */
    public function createFile(): bool
    {
        if (!$this->isFreed) {
            return true;
        }

        for ($try = 0; $try < self::MAX_RETRY; $try++) {
            $this->path = new Path([
                \sys_get_temp_dir(),
                'tmp.' . \bin2hex(\random_bytes(16)) . '.file'
            ]);

            if (!file_exists($this->path->raw) && $this->touch(true)) {
                $this->isFreed = false;
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function createDir(): bool
    {
        if (!$this->isFreed) {
            return true;
        }

        for ($try = 0; $try < self::MAX_RETRY; $try++) {
            $this->path = new Path([
                \sys_get_temp_dir(),
                'tmp.' . \bin2hex(\random_bytes(16)) . '.dir'
            ]);

            if (!file_exists($this->path->raw) && $this->mkdir()) {
                $this->isFreed = false;
                return true;
            }
        }

        return false;
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
        $this->isFreed = true;
        return parent::removeFile();
    }

    /**
     * @inheritDoc
     */
    public function removeDir(): bool
    {
        $this->isFreed = true;
        return parent::removeDir();
    }

    /**
    * @inheritDoc
     */
    public function mkdir(): bool
    {
        $this->isFreed = false;
        return parent::mkdir();
    }

    /**
     * remove tempfile on free
     */
    public function __destruct()
    {
        if ($this->isFreed || !file_exists($this->path->raw)) {
            return;
        }

        if (is_file($this->path->raw)) {
            $this->removeFile();
        } elseif (is_dir($this->path->raw)) {
            $this->removeDir();
        }
    }
}
