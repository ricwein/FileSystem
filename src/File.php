<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem;

use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Helper\Hash;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Storage\Extensions\Binary;

/**
 * represents a selected file
 */
class File extends FileSystem
{
    /**
     * @inheritDoc
     * @throws AccessDeniedException
     */
    public function __construct(Storage $storage, int $constraints = Constraint::STRICT)
    {
        if ($storage instanceof Storage\Disk\Temp && !$storage->createFile()) {
            throw new AccessDeniedException('unable to create temp file', 500);
        }

        parent::__construct($storage, $constraints);
    }

    /**
     * @param int|null $offset
     * @param int|null $length
     * @param int $mode
     * @return string
     * @throws FileNotFoundException
     */
    public function read(?int $offset = null, ?int $length = null, int $mode = LOCK_SH): string
    {

        // validate constraints
        if (!$this->isFile() || !$this->storage->doesSatisfyConstraints() || !$this->isReadable()) {
            throw new FileNotFoundException('unable to open file', 404, $this->storage->getConstraintViolations());
        }

        return $this->storage->readFile($offset, $length, $mode);
    }

    /**
     * @param int|null $offset
     * @param int|null $length
     * @param int $mode
     * @return void
     * @throws FileNotFoundException
     */
    public function stream(?int $offset = null, ?int $length = null, int $mode = LOCK_SH): void
    {

        // validate constraints
        if (!$this->isFile() || !$this->storage->doesSatisfyConstraints() || !$this->isReadable()) {
            throw new FileNotFoundException('unable to open file', 404, $this->storage->getConstraintViolations());
        }

        $this->storage->streamFile($offset, $length, $mode);
    }

    /**
     * write content to storage
     * @param  string $content
     * @param bool $append
     * @param int $mode LOCK_EX
     * @return self
     * @throws AccessDeniedException
     */
    public function write(string $content, bool $append = false, int $mode = LOCK_EX): self
    {

        // validate constraints
        if (!$this->storage->doesSatisfyConstraints() || !$this->isWriteable()) {
            throw new AccessDeniedException('unable to write file-content', 403, $this->storage->getConstraintViolations());
        }

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
     * @throws AccessDeniedException|FileNotFoundException
     */
    public function copyTo(Storage $destination, ?int $constraints = null): self
    {
        $destination->setConstraints(($constraints !== null) ? $constraints : $this->storage->getConstraints());

        // validate constraints
        if (!$this->isFile() || !$this->storage->doesSatisfyConstraints() || !$this->isReadable()) {
            throw new FileNotFoundException('unable to open source file', 404, $this->storage->getConstraintViolations());
        } elseif ($destination instanceof Storage\Disk\Temp && !$destination->createFile()) {
            throw new AccessDeniedException('unable to create temp file', 500);
        } elseif (!$destination->doesSatisfyConstraints() || !$destination->isWriteable()) {
            throw new AccessDeniedException('unable to open destination file', 404, $destination->getConstraintViolations());
        }

        // copy file from filesystem to filesystem
        if ($this->storage instanceof Storage\Disk && $destination instanceof Storage\Disk) {
            if (!copy($this->storage->path()->real, $destination->path()->raw)) {
                throw new AccessDeniedException('unable to copy file', 403);
            }

            $destination->path()->reload();
            return new static($destination);
        }

        // read content into memory and write it into destination
        $destination->writeFile($this->storage->readFile());

        // reload disk-path, since we may just created a new file
        if ($destination instanceof Storage\Disk) {
            $destination->path()->reload();
        }

        return new static($destination);
    }

    /**
     * copy file-content to new destination
     * @param Storage\Disk $destination
     * @param int|null $constraints
     * @return self new File-object
     * @throws AccessDeniedException|FileNotFoundException
     */
    public function moveTo(Storage\Disk $destination, ?int $constraints = null): self
    {
        $destination->setConstraints(($constraints !== null) ? $constraints : $this->storage->getConstraints());

        // validate constraints
        if (!$this->storage instanceof Storage\Disk) {
            throw new UnexpectedValueException('unable to move file from memory', 500);
        } elseif (!$this->isFile() || !$this->storage->doesSatisfyConstraints() || !$this->isReadable()) {
            throw new FileNotFoundException('unable to open source file', 404, $this->storage->getConstraintViolations());
        } elseif (!$destination->doesSatisfyConstraints() || !$destination->isWriteable()) {
            throw new AccessDeniedException('unable to write to destination path', 404, $destination->getConstraintViolations());
        }

        // build destination path
        if (!$destination->path()->fileInfo()->isFile()) {
            $destination = new Storage\Disk($destination, $this->storage->getOriginalName());
            $destination->setConstraints(($constraints !== null) ? $constraints : $this->storage->getConstraints());
        }

        if ($this->storage instanceof Storage\Disk\Uploaded) {

            // move uploaded file
            if (!move_uploaded_file($this->storage->path()->real, $destination->path()->raw)) {
                throw new AccessDeniedException('unable to move uploaded file', 403);
            }
        } else {

            // move file
            if (!rename($this->storage->path()->real, $destination->path()->raw)) {
                throw new AccessDeniedException('unable to move file', 403);
            }
        }

        $destination->path()->reload();
        return new static($destination);
    }

    /**
     * @inheritDoc
     */
    public function isFile(): bool
    {
        return $this->storage->isFile();
    }

    /**
     * guess content-type (mime) of storage
     * @param  bool $withEncoding
     * @return string
     * @throws UnexpectedValueException|FileNotFoundException
     */
    public function getType(bool $withEncoding = false): string
    {

        // validate constraints
        if (!$this->isFile() || !$this->storage->doesSatisfyConstraints() || !$this->isReadable()) {
            throw new FileNotFoundException('unable to open file', 404, $this->storage->getConstraintViolations());
        }

        if (null !== $mime = $this->storage->getFileType($withEncoding)) {
            return $mime;
        }

        throw new UnexpectedValueException('unable to determin files content-type', 500);
    }

    /**
     * @inheritDoc
     * @throws UnexpectedValueException|FileNotFoundException
     */
    public function getHash(int $mode = Hash::CONTENT, string $algo = 'sha256'): string
    {

        // validate constraints
        if (!$this->isFile() || !$this->storage->doesSatisfyConstraints() || !$this->isReadable()) {
            throw new FileNotFoundException(sprintf('unable to open file for path: "%s"', $this->path()->raw), 404, $this->storage->getConstraintViolations());
        }

        if (null !== $hash = $this->storage->getFileHash($mode, $algo)) {
            return $hash;
        }

        throw new UnexpectedValueException('unable to calculate file-hash', 500);
    }

    /**
     * calculate size
     * @return int
     * @throws UnexpectedValueException
     */
    public function getSize(): int
    {
        return $this->storage->getSize();
    }

    /**
    * @param  bool $ifNewOnly
    * @return bool
     */
    public function touch(bool $ifNewOnly = false): bool
    {
        return $this->storage->touch($ifNewOnly);
    }

    /**
     * @inheritDoc
     * @throws AccessDeniedException|RuntimeException
     */
    public function remove(): FileSystem
    {

        // validate constraints
        if (!$this->isFile() || !$this->storage->doesSatisfyConstraints() || !$this->isWriteable()) {
            throw new AccessDeniedException('unable to open file', 404, $this->storage->getConstraintViolations());
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
     * @throws RuntimeException
     */
    public function getHandle(int $mode): Binary
    {
        if (!$this->isFile() || !$this->storage->doesSatisfyConstraints()) {
            throw new RuntimeException('unable to open handle for file', 500, $this->storage->getConstraintViolations());
        }

        return $this->storage->getHandle($mode);
    }

    /**
     * @param int|null $constraints
     * @return Directory
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
}
