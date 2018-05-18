<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem;

use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Helper\Hash;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;

/**
 * represents a selected directory
 */
class File extends FileSystem
{
    /**
     * @inheritDoc
     * @throws AccessDeniedException
     */
    public function __construct(Storage\Storage $storage, int $constraints = Constraint::STRICT)
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
     * write content to storage
     * @param  string $content
     * @param int $mode FILE_USE_INCLUDE_PATH | FILE_APPEND | LOCK_EX
     * @return self
     * @throws AccessDeniedException
     */
    public function write(string $content, int $mode = 0): self
    {

        // validate constraints
        if (!$this->storage->doesSatisfyConstraints() || !$this->isWriteable()) {
            throw new AccessDeniedException('unable to write file-content', 403, $this->storage->getConstraintViolations());
        }

        if (!$this->storage->writeFile($content, $mode)) {
            throw new AccessDeniedException('unable to write file-content', 403);
        }

        return $this;
    }

    /**
     * copy file-content to new destination
     * @param Storage\Storage $destination
     * @param int|null $constraints
     * @return self new File-object
     * @throws AccessDeniedException|FileNotFoundException
     */
    public function copyTo(Storage\Storage $destination, ?int $constraints = null): self
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

        return new self($destination);
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
     * check if file exists and is an actual file
     * @return bool
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
            throw new FileNotFoundException('unable to open file', 404, $this->storage->getConstraintViolations());
        }

        if (null !== $hash = $this->storage->getFileHash($mode, $algo)) {
            return $hash;
        }

        throw new UnexpectedValueException('unable to calculate file-hash', 500);
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
}
