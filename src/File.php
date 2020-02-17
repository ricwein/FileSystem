<?php

/**
 * @author Richard Weinhold
 */

namespace ricwein\FileSystem;

use League\Flysystem\FileExistsException;
use League\Flysystem\FileNotFoundException as FlySystemFileNotFoundException;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Enum\Hash;
use ricwein\FileSystem\Storage\Extensions\Binary;

/**
 * represents a selected file
 */
class File extends FileSystem
{
    /**
     * @inheritDoc
     * @throws AccessDeniedException
     * @throws Exceptions\Exception
     */
    public function __construct(Storage $storage, int $constraints = Constraint::STRICT)
    {
        if ($storage instanceof Storage\Disk\Temp && !$storage->touch(true)) {
            throw new AccessDeniedException('unable to create temp file', 500);
        }

        parent::__construct($storage, $constraints);
    }

    /**
     * validate constraints and check file permissions
     * @return void
     * @throws FileNotFoundException
     * @throws AccessDeniedException
     * @throws ConstraintsException
     */
    protected function checkFileReadPermissions(): void
    {
        if (!$this->isFile() || !$this->isReadable()) {
            throw new FileNotFoundException(sprintf('unable to open file: "%s"', $this->storage instanceof Storage\Disk ? $this->storage->path()->raw : get_class($this->storage)), 404);
        } elseif (!$this->storage->doesSatisfyConstraints()) {
            throw new AccessDeniedException(sprintf('unable to open file: "%s"', $this->storage instanceof Storage\Disk ? $this->storage->path()->raw : get_class($this->storage)), 404, $this->storage->getConstraintViolations());
        }
    }

    /**
     * validate constraints and check file permissions
     * @return void
     * @throws AccessDeniedException
     * @throws ConstraintsException
     */
    protected function checkFileWritePermissions(): void
    {
        if (!$this->storage->doesSatisfyConstraints()) {
            throw new AccessDeniedException(sprintf('unable to write file: "%s"', $this->storage instanceof Storage\Disk ? $this->storage->path()->raw : get_class($this->storage)), 403, $this->storage->getConstraintViolations());
        } elseif ($this->isFile() && !$this->isWriteable()) {
            throw new AccessDeniedException(sprintf('unable to write file: "%s"', $this->storage instanceof Storage\Disk ? $this->storage->path()->raw : get_class($this->storage)), 403);
        }
    }

    /**
     * @param int|null $offset
     * @param int|null $length
     * @param int $mode
     * @return string
     * @throws FileNotFoundException|AccessDeniedException
     * @throws ConstraintsException
     */
    public function read(?int $offset = null, ?int $length = null, int $mode = LOCK_SH): string
    {
        $this->checkFileReadPermissions();

        return $this->storage->readFile($offset, $length, $mode);
    }

    /**
     * @param int|null $offset
     * @param int|null $length
     * @param int $mode
     * @return void
     * @throws FileNotFoundException|AccessDeniedException
     * @throws ConstraintsException
     */
    public function stream(?int $offset = null, ?int $length = null, int $mode = LOCK_SH): void
    {
        $this->checkFileReadPermissions();

        $this->storage->streamFile($offset, $length, $mode);
    }

    /**
     * write content to storage
     * @param string $content
     * @param bool $append
     * @param int $mode LOCK_EX
     * @return self
     * @throws AccessDeniedException
     * @throws ConstraintsException
     */
    public function write(string $content, bool $append = false, int $mode = LOCK_EX): self
    {
        $this->checkFileWritePermissions();

        if (!$this->storage->writeFile($content, $append, $mode)) {
            throw new AccessDeniedException('unable to write file-content', 403);
        }

        return $this;
    }

    /**
     * copy file-content to new destination
     * @param Storage $destination
     * @param int|null $constraints
     * @return self new File-object
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exceptions\Exception
     * @throws FileExistsException
     * @throws FileNotFoundException
     * @throws FlySystemFileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function copyTo(Storage $destination, ?int $constraints = null): self
    {
        if (!$this->copyFileTo($destination, $constraints)) {
            throw new AccessDeniedException('unable to copy file', 403);
        }

        return new static($destination);
    }

    /**
     * @param Storage &$destination mutable
     * @param int|null $constraints
     * @return bool success
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exceptions\Exception
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws FileExistsException
     * @throws FlySystemFileNotFoundException
     */
    protected function copyFileTo(Storage &$destination, ?int $constraints = null): bool
    {
        $destination->setConstraints(($constraints !== null) ? $constraints : $this->storage->getConstraints());

        $this->checkFileReadPermissions();

        // validate constraints
        if ($destination instanceof Storage\Disk\Temp && !$destination->touch(true)) {
            throw new AccessDeniedException('unable to create temp file', 403);
        } elseif (!$destination->doesSatisfyConstraints()) {
            throw new AccessDeniedException('unable to open destination file', 403, $destination->getConstraintViolations());
        } elseif ($destination->isFile() && !$destination->isWriteable()) {
            throw new AccessDeniedException('unable to write to destination file', 403);
        }

        // ensure the destination-path points to a filename
        if ($destination instanceof Storage\Disk && $destination->isDir()) {
            $destination = clone $destination;
            $destination->cd([$this->storage instanceof Storage\Disk ? $this->path()->filename : (uniqid() . '.file')]);
        }

        // actual copy file to file: use native functions if possible
        return $this->storage->copyFileTo($destination);
    }

    /**
     * copy file-content to new destination
     * @param Storage $destination
     * @param int|null $constraints
     * @return self new File-object
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exceptions\Exception
     * @throws FileExistsException
     * @throws FileNotFoundException
     * @throws FlySystemFileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function moveTo(Storage $destination, ?int $constraints = null): self
    {
        // actual move file to file: use native functions if possible
        if (!$this->moveFileTo($destination, $constraints)) {
            throw new AccessDeniedException('unable to move file', 403);
        }

        return new static($destination);
    }

    /**
     * @param Storage &$destination mutable
     * @param int|null $constraints
     * @return bool success
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exceptions\Exception
     * @throws FileExistsException
     * @throws FileNotFoundException
     * @throws FlySystemFileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function moveFileTo(Storage &$destination, ?int $constraints = null): bool
    {
        $destination->setConstraints(($constraints !== null) ? $constraints : $this->storage->getConstraints());

        $this->checkFileReadPermissions();

        // validate constraints
        if ($destination instanceof Storage\Disk\Temp && !$destination->touch(true)) {
            throw new AccessDeniedException('unable to create temp file', 403);
        } elseif (!$destination->doesSatisfyConstraints()) {
            throw new AccessDeniedException('unable to write to destination file', 403, $destination->getConstraintViolations());
        } elseif ($destination->isFile() && !$destination->isWriteable()) {
            throw new AccessDeniedException('unable to write to destination file', 403);
        }

        // ensure the destination-path points to a filename
        if ($destination instanceof Storage\Disk && $destination->isDir()) {
            $destination = clone $destination;
            $destination->cd([$this->storage instanceof Storage\Disk ? $this->path()->filename : (uniqid() . '.file')]);
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
     * @param bool $withEncoding
     * @return string
     * @throws AccessDeniedException
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

        throw new UnexpectedValueException('unable to determin files content-type', 500);
    }

    /**
     * @inheritDoc
     * @param int $mode
     * @param string $algo
     * @return string
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function getHash(int $mode = Hash::CONTENT, string $algo = 'sha256'): string
    {
        $this->checkFileReadPermissions();

        if (null !== $hash = $this->storage->getFileHash($mode, $algo)) {
            return $hash;
        }

        throw new UnexpectedValueException('unable to calculate file-hash', 500);
    }

    /**
     * calculate size
     * @return int
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws FileNotFoundException
     */
    public function getSize(): int
    {
        $this->checkFileReadPermissions();

        return $this->storage->getSize();
    }

    /**
     * @param bool $ifNewOnly
     * @param null|int $time last-modified time
     * @param null|int $atime last-access time
     * @return bool
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exceptions\Exception
     */
    public function touch(bool $ifNewOnly = false, ?int $time = null, ?int $atime = null): bool
    {
        $this->checkFileWritePermissions();

        return $this->storage->touch($ifNewOnly, $time, $atime);
    }

    /**
     * @inheritDoc
     * @return FileSystem
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws RuntimeException
     */
    public function remove(): FileSystem
    {
        $this->checkFileWritePermissions();

        // validate constraints
        if (!$this->isFile()) {
            throw new FileNotFoundException(sprintf('unable to open file: "%s"', $this->storage instanceof Storage\Disk ? $this->storage->path()->raw : get_class($this->storage)), 404);
        }

        if (!$this->storage->removeFile()) {
            throw new RuntimeException('unable to remove file', 500);
        }
        return $this;
    }

    /**
     * access file for binary read/write actions
     * @param int $mode
     * @return Binary
     * @throws AccessDeniedException
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
     * @param int|null $constraints
     * @return Directory
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function directory(?int $constraints = null): Directory
    {
        if (!$this->storage->doesSatisfyConstraints()) {
            throw $this->storage->getConstraintViolations();
        }

        $dirpath = $this->path()->directory;
        if (is_dir($dirpath)) {
            $dirpath = realpath($dirpath);
        }

        $safepath = $this->path()->safepath;
        if (is_dir($safepath)) {
            $safepath = realpath($safepath);
        }

        /** @var Storage $storage */
        $storage = null;

        if (is_dir($safepath)) {
            $storage = new Storage\Disk($safepath, str_replace($safepath, '', $dirpath));
        } else {
            $storage = new Storage\Disk($dirpath);
        }

        return new Directory(
            $storage,
            $constraints !== null ? $constraints : $this->storage->getConstraints()
        );
    }


    /**
     * open and return file-stream
     * @param string $mode
     * @return resource
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws FileNotFoundException
     */
    public function getStream(string $mode = 'r+')
    {
        $this->checkFileReadPermissions();

        return $this->storage->getStream($mode);
    }
}
