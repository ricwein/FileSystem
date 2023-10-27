<?php
declare(strict_types=1);

namespace ricwein\FileSystem;

use League\Flysystem\FilesystemException as FlysystemException;
use ricwein\FileSystem\Enum\Hash;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Helper\DirectoryIterator;
use ricwein\FileSystem\Storage\BaseStorage;
use ricwein\FileSystem\Storage\DirectoryStorageInterface;

/**
 * Represents a selected directory.
 * @author Richard Weinhold
 * @method BaseStorage&DirectoryStorageInterface storage()
 */
class Directory extends FileSystem
{
    /**
     * @var BaseStorage&DirectoryStorageInterface
     */
    protected BaseStorage $storage;

    /**
     * @inheritDoc
     * @throws AccessDeniedException
     * @throws UnsupportedException
     */
    public function __construct(BaseStorage&DirectoryStorageInterface $storage, int $constraints = Constraint::STRICT)
    {
        if ($storage instanceof Storage\Disk\Temp && !$storage->isDir() && !$storage->mkdir()) {
            throw new AccessDeniedException('Unable to create temp directory.', 500);
        }

        parent::__construct($storage, $constraints);
    }

    protected function getDirectoryPath(): string
    {
        if ($this->storage instanceof Storage\Disk || $this->storage instanceof Storage\Flysystem) {
            return $this->storage->getPath()->getRealOrRawPath();
        }

        return (string)$this->storage;
    }

    /**
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @inheritDoc
     */
    protected function checkFileReadPermissions(): void
    {
        if (!$this->isDir() || !$this->isReadable()) {
            throw new FileNotFoundException("Unable to open directory: \"{$this->getDirectoryPath()}\".", 404);
        }

        parent::checkFileReadPermissions();
    }

    /**
     * create new dir if not exists
     * @throws AccessDeniedException
     * @throws ConstraintsException
     */
    public function mkdir(int $permissions = 0755): self
    {
        if (!$this->storage->doesSatisfyConstraints()) {
            throw $this->storage->getConstraintViolations();
        }

        if (!$this->storage->isDir() && !$this->storage->mkdir(false, $permissions)) {
            throw new AccessDeniedException(sprintf('unable to create directory at: "%s"', $this->storage->getPath()->getRawPath()), 500);
        }

        return $this;
    }

    /**
     * @inheritDoc
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws RuntimeException
     * @throws FileNotFoundException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function remove(): static
    {
        if (!$this->storage->doesSatisfyConstraints()) {
            throw $this->storage->getConstraintViolations();
        }

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
     * @throws ConstraintsException
     */
    public function list(bool $recursive = false, ?int $constraints = null): DirectoryIterator
    {
        if (!$this->storage->doesSatisfyConstraints()) {
            throw $this->storage->getConstraintViolations();
        }

        return new DirectoryIterator($this->storage, $recursive, $constraints);
    }

    /**
     * @inheritDoc
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnsupportedException
     * @throws UnexpectedValueException
     */
    public function getHash(Hash $mode = Hash::CONTENT, string $algo = 'sha256', bool $raw = false, bool $recursive = false): string
    {
        if (!$recursive && in_array($mode, [Hash::FILENAME, Hash::FILEPATH, Hash::LAST_MODIFIED], true)) {
            return match ($mode) {
                Hash::FILENAME => hash($algo, $this->getPath()->getFilename(), $raw),
                Hash::FILEPATH => hash($algo, $this->getPath()->getRealPath() ?: throw new UnexpectedValueException('Failed to calculate directory-hash.', 500), $raw),
                Hash::LAST_MODIFIED => hash($algo, (string)$this->getTime(), $raw),
            };
        }

        /** @var string[] $fileHashes */
        $fileHashes = [];

        /** @var File $entry */
        foreach ($this->list($recursive)->files($this->storage->getConstraints()) as $entry) {
            $fileHashes[] = $entry->getHash($mode, $algo);
        }

        return hash($algo, implode(':', $fileHashes), $raw);
    }

    /**
     * calculate size
     * @throws AccessDeniedException
     * @throws UnsupportedException
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
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function cd(string|FileSystem|Path|Storage\Disk ...$path): self
    {
        $this->storage->cd($path);
        return $this;
    }

    /**
     * move directory upwards (like /../)
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function up(int $move = 1): self
    {
        $this->storage->cd(array_fill(0, $move, '/..'));
        return $this;
    }

    /**
     * @throws ConstraintsException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws FlysystemException
     */
    public function file(string $filename, ?int $constraints = null, string $as = File::class, mixed ...$arguments): File
    {
        if (!$this->storage->doesSatisfyConstraints()) {
            throw $this->storage->getConstraintViolations();
        }

        if ($this->storage instanceof Storage\Flysystem) {
            return new $as(new Storage\Flysystem($this->storage->getFlySystem(), "{$this->storage->getPath()->getRawPath()}/$filename"), $constraints ?? $this->storage->getConstraints(), ...$arguments);
        }

        $dirPath = $this->getDirectoryPath();
        if (is_dir($dirPath)) {
            $dirPath = realpath($dirPath);
        }

        $safepath = $this->storage->getPath()->getSafePath();
        if (is_dir($safepath)) {
            $safepath = realpath($safepath);
        }

        /** @var BaseStorage $storage */
        if (is_dir($safepath) && str_starts_with($dirPath, $safepath)) {
            $storage = new Storage\Disk($safepath, str_replace($safepath, '', $dirPath), $filename);
        } else {
            $storage = new Storage\Disk($dirPath, $filename);
        }

        return new $as($storage, $constraints ?? $this->storage->getConstraints(), ...$arguments);
    }

    /**
     * @throws ConstraintsException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function dir(string $dirname, ?int $constraints = null, string $as = self::class, mixed ...$arguments): Directory
    {
        if (!$this->storage->doesSatisfyConstraints()) {
            throw $this->storage->getConstraintViolations();
        }

        $directory = new $as(clone $this->storage, $constraints ?? $this->storage->getConstraints(), ...$arguments);
        return $directory->cd($dirname);
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws UnsupportedException
     */
    public function copyTo(BaseStorage $destination, ?int $constraints = null): static
    {
        if (!$destination instanceof Storage\Disk || !$this->storage instanceof Storage\Disk) {
            throw new UnsupportedException(sprintf("Copying a directory requires both, source and destination to be of type Storage\\Disk, but got %s instead.", $destination::class), 500);
        }

        $destination->setConstraints($constraints ?? $this->storage->getConstraints());

        $this->checkFileReadPermissions();

        if (!$destination->doesSatisfyConstraints()) {
            throw new AccessDeniedException('Unable to open destination directory.', 403, $destination->getConstraintViolations());
        }

        if ($destination->isDir() && !$destination->isWriteable()) {
            throw new AccessDeniedException('Unable to write to destination file.', 403);
        }

        // actual copy directory to destination: use native functions if possible
        if (!$this->storage->copyDirectoryTo($destination)) {
            throw new AccessDeniedException('unable to copy file', 403);
        }

        return new static($destination);
    }
}
