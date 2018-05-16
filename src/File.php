<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem;

use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Helper\Hash;
use ricwein\FileSystem\Exception\AccessDeniedException;
use ricwein\FileSystem\Exception\FileNotFoundException;
use ricwein\FileSystem\Exception\RuntimeException;
use ricwein\FileSystem\Exception\UnexpectedValueException;

/**
 * represents a selected directory
 */
class File extends FileSystem
{
    /**
     * @param int|null $offset
     * @param int|null $length
     * @param int $mode
     * @return string
     * @throws FileNotFoundException
     */
    public function read(?int $offset = null, ?int $length = null, int $mode = LOCK_SH): string
    {
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
        if (!$this->storage->writeFile($content, $mode)) {
            throw new AccessDeniedException('unable to write file-content', 403);
        }

        return $this;
    }

    /**
     * copy file-content to new destination
     * @param Storage\Storage $destination
     * @return self
     * @throws AccessDeniedException|RuntimeException
     */
    public function saveAs(Storage\Storage $destination): self
    {

        // copy file from filesystem to filesystem
        if ($this->storage instanceof Storage\Disk && $destination instanceof Storage\Disk) {
            if (!copy($this->storage->path()->real, $destination->path()->raw)) {
                throw new AccessDeniedException('unable to copy file', 403);
            }

            $destination->path()->reload();
            return new static($destination);
        }

        $destination->writeFile($this->storage->readFile());
        if ($destination instanceof Storage\Disk) {
            $destination->path()->reload();
        }

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
     * @return int
     * @throws UnexpectedValueException
     */
    public function getSize(): int
    {
        return $this->storage->getSize();
    }

    /**
     * guess content-type (mime) of storage
     * @param  bool $withEncoding
     * @return string
     * @throws UnexpectedValueException
     */
    public function getType(bool $withEncoding = false): string
    {
        if (null !== $mime = $this->storage->getFileType($withEncoding)) {
            return $mime;
        }

        throw new UnexpectedValueException('unable to determin files content-type', 500);
    }

    /**
     * @inheritDoc
     * @throws UnexpectedValueException|RuntimeException
     */
    public function getHash(int $mode = Hash::CONTENT, string $algo = 'sha256'): string
    {
        if (null !== $hash = $this->storage->getFileHash($mode, $algo)) {
            return $hash;
        }

        throw new UnexpectedValueException('unable to calculate file-hash', 500);
    }

    /**
     * @return bool
     */
    public function isDotfile(): bool
    {
        if ($this->storage instanceof Storage\Disk) {
            return strpos($this->storage->path()->basename, '.') ===0;
        }
        return false;
    }

    /**
     * remove file
     * @return FileSystem
     */
    public function remove(): FileSystem
    {
        if (!$this->storage->removeFile()) {
            throw new RuntimeException('unable to remove file', 500);
        }
        return $this;
    }
}
