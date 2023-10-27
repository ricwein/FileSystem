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
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Helper\Stream;
use ricwein\FileSystem\Storage\Extensions\Binary;
use ricwein\FileSystem\Storage\BaseStorage;
use ricwein\FileSystem\Storage\FileStorageInterface;

/**
 * @author Richard Weinhold
 * represents a selected file
 */
class File extends FileSystem
{
    /**
     * @var BaseStorage&FileStorageInterface
     */
    protected BaseStorage $storage;

    /**
     * @inheritDoc
     * @throws AccessDeniedException
     */
    public function __construct(BaseStorage&FileStorageInterface $storage, int $constraints = Constraint::STRICT)
    {
        if ($storage instanceof Storage\Disk\Temp && !$storage->touch(true)) {
            throw new AccessDeniedException('Unable to create temp file.', 500);
        }

        parent::__construct($storage, $constraints);
    }

    /**
     * @throws ConstraintsException
     * @throws FileNotFoundException
     */
    protected function checkFileReadPermissions(): void
    {
        if (!$this->isFile() || !$this->isReadable()) {
            throw new FileNotFoundException(sprintf('unable to open file: "%s"', $this->storage instanceof Storage\Disk ? $this->storage->getPath()->getRealOrRawPath() : get_class($this->storage)), 404);
        }

        parent::checkFileReadPermissions(); // TODO: Change the autogenerated stub
    }

    /**
     * validate constraints and check file permissions
     * @throws AccessDeniedException
     * @throws ConstraintsException
     */
    protected function checkFileWritePermissions(): void
    {
        if (!$this->storage->doesSatisfyConstraints()) {
            throw new AccessDeniedException(
                sprintf('unable to write file: "%s"', $this->storage instanceof Storage\Disk ? $this->storage->getPath()->getRawPath() : get_class($this->storage)), 403, $this->storage->getConstraintViolations()
            );
        }

        if ($this->isFile() && !$this->isWriteable()) {
            throw new AccessDeniedException(sprintf('unable to write file: "%s"', $this->storage instanceof Storage\Disk ? $this->storage->getPath()->getRawPath() : get_class($this->storage)), 403);
        }
    }

    /**
     * @throws ConstraintsException
     * @throws FileNotFoundException
     */
    public function read(int $offset = 0, ?int $length = null, int $mode = LOCK_SH): string
    {
        $this->checkFileReadPermissions();
        return $this->storage->readFile($offset, $length, $mode);
    }

    /**
     * @return string[]
     * @throws ConstraintsException
     * @throws FileNotFoundException
     */
    public function readAsLines(): array
    {
        $this->checkFileReadPermissions();
        return $this->storage->readFileAsLines();
    }

    /**
     * @throws ConstraintsException
     * @throws FileNotFoundException
     */
    public function stream(int $offset = 0, ?int $length = null, int $mode = LOCK_SH): void
    {
        $this->checkFileReadPermissions();
        $this->storage->streamFile($offset, $length, $mode);
    }

    /**
     * write content to storage
     * @param int $mode LOCK_EX
     * @throws AccessDeniedException
     * @throws ConstraintsException
     */
    public function write(string $content, bool $append = false, int $mode = LOCK_EX): static
    {
        $this->checkFileWritePermissions();

        if (!$this->storage->writeFile($content, $append, $mode)) {
            throw new AccessDeniedException('unable to write file-content', 403);
        }

        return $this;
    }

    /**
     * copy file-content to new destination
     * @throws AccessDeniedException
     */
    public function copyTo(BaseStorage $destination, ?int $constraints = null): static
    {
        if (!$this->copyFileTo($destination, $constraints)) {
            throw new AccessDeniedException('unable to copy file', 403);
        }

        return new static($destination);
    }

    /**
     * @param BaseStorage &$destination mutable
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    protected function copyFileTo(BaseStorage &$destination, ?int $constraints = null): bool
    {
        $destination->setConstraints($constraints ?? $this->storage->getConstraints());

        $this->checkFileReadPermissions();

        // validate constraints
        if ($destination instanceof Storage\Disk\Temp && !$destination->touch(true)) {
            throw new AccessDeniedException('Unable to create temp file.', 403);
        }

        if (!$destination->doesSatisfyConstraints()) {
            throw new AccessDeniedException('Unable to open destination file.', 403, $destination->getConstraintViolations());
        }

        if ($destination->isFile() && !$destination->isWriteable()) {
            throw new AccessDeniedException('Unable to write to destination file.', 403);
        }

        // ensure the destination-path points to a filename
        if ($destination instanceof Storage\Disk && $destination->isDir()) {
            $destination = clone $destination;
            $destination->cd([$this->storage instanceof Storage\Disk ? $this->getPath()->getFilename() : (uniqid('', true) . '.file')]);
        }

        // actual copy file to file: use native functions if possible
        return $this->storage->copyFileTo($destination);
    }

    /**
     * copy file-content to new destination
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function moveTo(BaseStorage $destination, ?int $constraints = null): static
    {
        // actual move file to file: use native functions if possible
        if (!$this->moveFileTo($destination, $constraints)) {
            throw new AccessDeniedException('unable to move file', 403);
        }

        return new static($destination);
    }

    /**
     * @param BaseStorage &$destination mutable
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function moveFileTo(BaseStorage &$destination, ?int $constraints = null): bool
    {
        $destination->setConstraints($constraints ?? $this->storage->getConstraints());

        $this->checkFileReadPermissions();

        // validate constraints
        if ($destination instanceof Storage\Disk\Temp && !$destination->touch(true)) {
            throw new AccessDeniedException('unable to create temp file', 403);
        }

        if (!$destination->doesSatisfyConstraints()) {
            throw new AccessDeniedException('unable to write to destination file', 403, $destination->getConstraintViolations());
        }

        if ($destination->isFile() && !$destination->isWriteable()) {
            throw new AccessDeniedException('unable to write to destination file', 403);
        }

        // ensure the destination-path points to a filename
        if ($destination instanceof Storage\Disk && $destination->isDir()) {
            $destination = clone $destination;
            $destination->cd([$this->storage instanceof Storage\Disk ? $this->getPath()->getFilename() : (uniqid('', true) . '.file')]);
        }

        // actual move file to file: use native functions if possible
        return $this->storage->moveFileTo($destination);
    }

    /**
     * @inheritDoc
     * @throws ConstraintsException
     */
    public function isFile(): bool
    {
        if (!$this->storage->doesSatisfyConstraints()) {
            throw $this->storage->getConstraintViolations();
        }

        return $this->storage->isFile();
    }

    /**
     * guess content-type (mime) of storage
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws UnexpectedValueException
     */
    public function getType(bool $withEncoding = false): string
    {
        $this->checkFileReadPermissions();

        if (null !== $mime = $this->storage->getFileType($withEncoding)) {
            return $mime;
        }

        throw new UnexpectedValueException('unable to determine files content-type', 500);
    }

    /**
     * @inheritDoc
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function getHash(Hash $mode = Hash::CONTENT, string $algo = 'sha256', bool $raw = false): string
    {
        $this->checkFileReadPermissions();

        if (null !== $hash = $this->storage->getFileHash($mode, $algo, $raw)) {
            return $hash;
        }

        throw new UnexpectedValueException('unable to calculate file-hash', 500);
    }

    /**
     * calculate size
     * @throws ConstraintsException
     * @throws FileNotFoundException
     */
    public function getSize(): int
    {
        $this->checkFileReadPermissions();
        return $this->storage->getSize();
    }

    /**
     * @param null|int $time last-modified time
     * @param null|int $atime last-access time
     * @throws AccessDeniedException
     * @throws ConstraintsException
     */
    public function touch(bool $ifNewOnly = false, ?int $time = null, ?int $atime = null): bool
    {
        $this->checkFileWritePermissions();

        return $this->storage->touch($ifNewOnly, $time, $atime);
    }

    /**
     * @inheritDoc
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws RuntimeException
     */
    public function remove(): static
    {
        $this->checkFileWritePermissions();

        // validate constraints
        if (!$this->isFile()) {
            throw new FileNotFoundException(sprintf('unable to open file: "%s"', $this->storage instanceof Storage\Disk ? $this->storage->getPath()->getRawPath() : get_class($this->storage)), 404);
        }

        if (!$this->storage->removeFile()) {
            throw new RuntimeException('unable to remove file', 500);
        }
        return $this;
    }

    /**
     * access file for binary read/write actions
     * @throws ConstraintsException
     * @throws Exceptions\UnsupportedException
     * @throws FileNotFoundException
     */
    public function getHandle(int $mode): Binary
    {
        $this->checkFileReadPermissions();

        return $this->storage->getHandle($mode);
    }

    /**
     * @throws ConstraintsException
     * @throws FlysystemException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function dir(?int $constraints = null, string $as = Directory::class, mixed ...$arguments): Directory
    {
        if (!$this->storage->doesSatisfyConstraints()) {
            throw $this->storage->getConstraintViolations();
        }

        if ($this->storage instanceof Storage\Flysystem) {
            $filePath = $this->storage->getPath()->getRawPath();

            if (false !== $lastDelimiterPos = strrpos($filePath, '/')) {
                $dirPath = substr($filePath, 0, $lastDelimiterPos);
            } else {
                $dirPath = '';
            }

            return new $as(new Storage\Flysystem($this->storage->getFlySystem(), $dirPath), $constraints ?? $this->storage->getConstraints(), ...$arguments);
        }


        $path = $this->getPath();
        $dirPath = $path->getDirectory();
        if (is_dir($dirPath)) {
            $dirPath = realpath($dirPath);
        }

        $safepath = $path->getSafePath();
        if (is_dir($safepath)) {
            $safepath = realpath($safepath);
        }

        if ($safepath !== false && is_dir($safepath)) {
            $storage = new Storage\Disk($safepath, str_replace($safepath, '', $dirPath));
        } else {
            $storage = new Storage\Disk($dirPath);
        }

        return new $as($storage, $constraints ?? $this->storage->getConstraints(), ...$arguments);
    }


    /**
     * open and return file-stream
     * @throws ConstraintsException
     * @throws FileNotFoundException
     */
    public function getStream(string $mode = 'rb+'): Stream
    {
        $this->checkFileReadPermissions();

        return $this->storage->getStream($mode);
    }
}
