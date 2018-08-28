<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage\Disk;

use ricwein\FileSystem\FileSystem;
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
     * @var bool
     */
    protected $isFree = true;

    /**
     * @var bool
     */
    protected $persist = false;

    public function __construct(... $path)
    {
        $filename = 'tmp.' . \bin2hex(\random_bytes(16));

        if (empty($path)) {
            $this->path = new Path([\sys_get_temp_dir(), $filename]);
            return;
        }

        $testPath = new Path($path);
        if (file_exists($testPath->raw)) {
            $this->path = $testPath;
            $this->isFree = false;
            return;
        }

        $fistComponent = reset($path);

        // check if our first (left) pathcomponent references to root (/)
        if (
            (is_string($fistComponent) && strpos($fistComponent, DIRECTORY_SEPARATOR) !== 0) ||
            ($fistComponent instanceof Path && strpos($fistComponent->raw, DIRECTORY_SEPARATOR) !== 0) ||
            ($fistComponent instanceof FileSystem && strpos($fistComponent->path()->raw, DIRECTORY_SEPARATOR) !== 0)
        ) {

            // we have a filename/relative-path, so lets add the temp-dir
            array_unshift($path, \sys_get_temp_dir());
            $this->path = new Path($path);
            return;
        }

        // we already have a directory, so we add a random temp-filename
        array_push($path, $filename);
        $this->path = new Path($path);
    }

    /**
     * @inheritDoc
     */
    public function touch(bool $ifNewOnly = false): bool
    {
        if (!$this->isFree) {
            return true;
        }
        $this->isFree = false;

        return parent::touch($ifNewOnly);
    }

    /**
     * @inheritDoc
     */
    public function mkdir(): bool
    {
        if (!$this->isFree) {
            return true;
        }
        $this->isFree = false;

        return parent::mkdir();
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
     * remove tempfile on free
     */
    public function __destruct()
    {
        if ($this->persist || $this->isFree || !file_exists($this->path->raw)) {
            return;
        }

        if (is_file($this->path->raw)) {
            $this->removeFile();
        } elseif (is_dir($this->path->raw)) {
            $this->removeDir();
        }
    }

    /**
     * make temp-file persistent,
     * even after destroying the (this) referencing object
     * @param bool $persist
     * @return self
     */
    public function persist(bool $persist = true): self
    {
        $this->persist = $persist;
        return $this;
    }
}
