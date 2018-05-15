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
     * @return string
     * @throws FileNotFoundException
     */
    public function read(): string
    {
        return $this->storage->read();
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
        if (!$this->storage->write($content, $mode)) {
            throw new AccessDeniedException('unable to write file-content', 403);
        }

        return $this;
    }

    /**
     * copy file-content to new destination
     * @param Storage\Storage $destination
     * @return self
     * @throws AccessDeniedException
     */
    public function saveAs(Storage\Storage $destination): self
    {
        if ($this->storage instanceof Storage\Disk && $destination instanceof Storage\Disk) {

            // copy file from filesystem to filesystem
            if (!copy($this->storage->path()->real, $destination->path()->raw)) {
                throw new AccessDeniedException('unable to copy file', 403);
            }

            $destination->path()->reload();
        } elseif ($this->storage instanceof Storage\Disk && $destination instanceof Storage\Memory) {

            // read file from filesystem into in-memory-file
            $destination->write($this->storage->read());
        } elseif ($this->storage instanceof Storage\Memory && $destination instanceof Storage\Disk) {

            // save in-memory-file to filesystem
            $destination->write($this->storage->read());
            $destination->path()->reload();
        } elseif ($this->storage instanceof Storage\Memory && $destination instanceof Storage\Memory) {

            // copy in-memory-file
            $destination->write($this->storage->read());
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
        if (null !== $filesize = $this->storage->getSize()) {
            return $filesize;
        }

        throw new UnexpectedValueException('unable to calculate file-size', 500);
    }

    /**
     * guess content-type (mime) of storage
     * @param  bool $withEncoding
     * @return string
     * @throws UnexpectedValueException
     */
    public function getType(bool $withEncoding = false): string
    {
        if (null !== $mime = $this->storage->getType($withEncoding)) {
            return $mime;
        }

        throw new UnexpectedValueException('unable to determin files content-type', 500);
    }

    /**
    * calculate hash
    * @param int $mode Hash::CONTENT | Hash::FILENAME | Hash::FILEPATH
    * @param string $algo hashing-algorigthm
    * @return string
    * @throws UnexpectedValueException|RuntimeException
     */
    public function getHash(int $mode = Hash::CONTENT, string $algo = 'sha256'): string
    {
        if (null !== $hash = $this->storage->getHash($mode, $algo)) {
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
}
