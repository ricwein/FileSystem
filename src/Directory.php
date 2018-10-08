<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem;

use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Enum\Hash;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Helper\DirectoryIterator;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;

/**
 * represents a selected directory
 */
class Directory extends FileSystem
{
    /**
     * @var Storage\Disk
     */
    protected $storage;

    /**
     * @inheritDoc
     */
    public function __construct(Storage $storage, int $constraints = Constraint::STRICT)
    {
        if ($storage instanceof Storage\Memory) {
            throw new UnexpectedValueException('in-memory directories are not supported', 500);
        }

        if ($storage instanceof Storage\Disk\Temp && !$storage->isDir() && !$storage->mkdir()) {
            throw new AccessDeniedException('unable to create temp directory', 500);
        }

        parent::__construct($storage, $constraints);
    }

    /**
     * create new dir if not exists
     * @return self
     */
    public function mkdir(): self
    {
        if (!$this->storage->isDir()) {
            if (!$this->storage->mkdir()) {
                throw new AccessDeniedException(sprintf('unable to create directory at: "%s"', $this->storage->path()->raw), 500);
            }
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function remove(): FileSystem
    {
        $this->storage->removeDir();
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isDir(): bool
    {
        return $this->storage->isDir();
    }

    /**
     * @param bool $recursive
     * @param bool $recursive
     * @return DirectoryIterator
     */
    public function list(bool $recursive = false): DirectoryIterator
    {
        return new DirectoryIterator($this->storage, $recursive);
    }

    /**
     * @inheritDoc
     * @param bool $recursive
     */
    public function getHash(int $mode = Hash::CONTENT, string $algo = 'sha256', bool $recursive = true): string
    {
        $fileHashes = [];

        /** @var File $entry */
        foreach ($this->list($recursive)->files($this->storage->getConstraints()) as $entry) {
            $fileHashes[] = $entry->getHash($mode, $algo);
        }

        return hash($algo, implode(':', $fileHashes), false);
    }

    /**
     * calculate size
     * @param bool $recursive
     * @return int
     * @throws UnexpectedValueException
     */
    public function getSize(bool $recursive = true): int
    {
        $size = 0;

        /** @var File $entry */
        foreach ($this->list($recursive)->files($this->storage->getConstraints()) as $file) {
            $size += $file->getSize();
        }
        return $size;
    }

    /**
     * changes current directory
     * @param string|FileSystem|Helper\Path $path ,...
     * @return self
     */
    public function cd(...$path): self
    {
        $this->storage->cd($path);
        return $this;
    }

    /**
     * move directory upwards (like /../)
     * @param int $move
     * @return self
     */
    public function up(int $move = 1): self
    {
        $this->storage->cd(array_fill(0, $move, '/..'));
        return $this;
    }


    /**
     * @param string $filename
     * @param int|null $constraints
     * @return File
     */
    public function file(string $filename, ?int $constraints = null): File
    {
        if (!$this->storage->doesSatisfyConstraints()) {
            throw $this->storage->getConstraintViolations();
        }

        $dirpath = $this->path()->raw;
        if (is_dir($dirpath)) {
            $dirpath = realpath($dirpath);
        }

        $safepath = $this->path()->safepath;
        if (is_dir($safepath)) {
            $safepath = realpath($safepath);
        }

        /** @var Storage $storage */
        $storage = null;

        if (is_dir($safepath) && strpos($dirpath, $safepath) === 0) {
            $storage = new Storage\Disk($safepath, str_replace($safepath, '', $dirpath), $filename);
        } else {
            $storage = new Storage\Disk($dirpath, $filename);
        }

        return new File(
            $storage,
            $constraints !== null ? $constraints : $this->storage->getConstraints()
        );
    }
}
