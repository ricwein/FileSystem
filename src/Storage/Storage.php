<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage;

use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Helper\Hash;
use ricwein\FileSystem\Exceptions\Exception;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\ConstraintsException;

/**
 * base-implementation for all Storage Adapters
 */
abstract class Storage
{
    /**
     * @var Constraint|null
     */
    protected $constraints = null;

    /**
     * returns all detail-informations for testing/debugging purposes
     * @return string[]
     */
    public function getDetails(): array
    {
        return [
            'storage' => static::class,
        ];
    }

    /**
     * @param  int  $constraints
     * @return self
     */
    public function setConstraints(int $constraints): self
    {
        $this->constraints = new Constraint($constraints);
        return $this;
    }

    /**
     * @return int
     */
    public function getConstraints(): int
    {
        return $this->constraints->getConstraints();
    }

    /**
     * @param \Throwable|null $previous
     * @return ConstraintsException|null
     */
    public function getConstraintViolations(\Throwable $previous = null): ?ConstraintsException
    {
        return $this->constraints->getErrors($previous);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return sprintf('-- not implemented for %s --', get_class($this));
    }

    /**
     * check if current path satisfies the given constraints
     * @return bool
     */
    abstract public function doesSatisfyConstraints(): bool;

    /**
     * check if file exists and is an actual file
     * @return bool
     */
    abstract public function isFile(): bool;

    /**
     * check if path is directory
     * @return bool
     */
    abstract public function isDir():bool;

    /**
     * check if file exists and is executable
     * @return bool
     */
    abstract public function isExecutable(): bool;

    /**
     * check if path is a symlink
     * @return bool
     */
    abstract public function isSymlink(): bool;

    /**
     * check if path is readable
     * @return bool
     */
    abstract public function isReadable(): bool;

    /**
     * check if path is writeable
     * @return bool
     */
    abstract public function isWriteable(): bool;

    /**
     * @return bool
     */
    abstract public function isDotfile(): bool;

    /**
     * @param int|null $offset
     * @param int|null $length
     * @param int $mode
     * @return string
     * @throws FileNotFoundException
     */
    abstract public function readFile(?int $offset = null, ?int $length = null, int $mode = LOCK_SH): string;

    /**
     * write content to storage
     * @param  string $content
     * @param bool $append
     * @param int $mode LOCK_EX
     * @return bool
     */
    abstract public function writeFile(string $content, bool $append = false, int $mode = 0): bool;

    /**
     * remove file from storage
     * @return bool
     */
    abstract public function removeFile(): bool;

    /**
     * size of file from storage
     * @return int
     */
    abstract public function getSize(): int;

    /**
     * get last-modified timestamp
     * @return int
     */
    abstract public function getTime(): int;

    /**
     * guess content-type (mime) of file
     * @param bool $withEncoding
     * @return string|null
     */
    abstract public function getFileType(bool $withEncoding = false): ?string;

    /**
     * calculate file-hash
     * @param int $mode Hash::CONTENT | Hash::FILENAME | Hash::FILEPATH
     * @param string $algo hashing-algorigthm
     * @return string|null
     * @throws RuntimeException
     */
    abstract public function getFileHash(int $mode = Hash::CONTENT, string $algo = 'sha256'): ?string;

    /**
     * @param  bool $ifNewOnly
     * @return bool
     * @throws Exception
     */
    abstract public function touch(bool $ifNewOnly = false): bool;
}
