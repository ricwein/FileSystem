<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage\Disk;

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
     * try to create random tempfile,
     * retry if file already exists
     * @var int
     */
    protected const MAX_RETRY = 4;

    /**
     * @var bool
     */
    protected $isFree = true;

    /**
     * @var Path|null
     */
    protected $path = null;

    public function __construct(... $path)
    {
        if (!empty($path)) {
            $this->path = new Path($path);
        }
    }

    /**
     * @return bool
     */
    public function createFile(): bool
    {
        if (!$this->isFree) {
            return true;
        }

        for ($try = 0; $try < self::MAX_RETRY; $try++) {
            $this->path = new Path([
                \sys_get_temp_dir(),
                $this->path !== null ? basename($this->path->raw) : 'tmp.' . \bin2hex(\random_bytes(16)) . '.file'
            ]);

            if (!file_exists($this->path->raw) && $this->touch(true)) {
                $this->isFree = false;
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
        if (!$this->isFree) {
            return true;
        }

        for ($try = 0; $try < self::MAX_RETRY; $try++) {
            $this->path = new Path([
                \sys_get_temp_dir(),
                $this->path !== null ? basename($this->path->raw) : 'tmp.' . \bin2hex(\random_bytes(16)) . '.dir'
            ]);

            if (!file_exists($this->path->raw) && $this->mkdir()) {
                $this->isFree = false;
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
        $this->isFree = true;
        return parent::removeFile();
    }

    /**
     * @inheritDoc
     */
    public function removeDir(): bool
    {
        $this->isFree = true;
        return parent::removeDir();
    }

    /**
    * @inheritDoc
     */
    public function mkdir(): bool
    {
        $this->isFree = false;
        return parent::mkdir();
    }

    /**
     * remove tempfile on free
     */
    public function __destruct()
    {
        if ($this->isFree || !file_exists($this->path->raw)) {
            return;
        }

        if (is_file($this->path->raw)) {
            $this->removeFile();
        } elseif (is_dir($this->path->raw)) {
            $this->removeDir();
        }
    }
}
