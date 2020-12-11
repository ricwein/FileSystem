<?php

/**
 * @author Richard Weinhold
 */

declare(strict_types=1);

namespace ricwein\FileSystem;

use Generator;
use ricwein\FileSystem\Enum\Time;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Enum\Hash;
use ricwein\FileSystem\Exceptions\Exception;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Storage\Extensions\Binary;
use Throwable;

/**
 * base-implementation for all Storage Adapters
 */
abstract class Storage
{
    protected ?Constraint $constraints = null;

    /**
     * remove file from filesystem on object destruction
     * => leaving scope or removing object reference
     */
    protected bool $selfdestruct = false;

    /**
     * returns all detail-informations for testing/debugging purposes
     */
    public function getDetails(): array
    {
        return [
            'storage' => static::class,
        ];
    }

    /**
     * @param int $constraints
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
     * @param Throwable|null $previous
     * @return ConstraintsException|null
     */
    public function getConstraintViolations(Throwable $previous = null): ?ConstraintsException
    {
        return $this->constraints->getErrors($previous);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return sprintf('[Storage: %s]', trim(str_replace(self::class, '', get_class($this)), '\\'));
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
    abstract public function isDir(): bool;

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
     * @param int $offset
     * @param int|null $length
     * @param int $mode
     * @return string
     * @throws FileNotFoundException
     */
    abstract public function readFile(int $offset = 0, ?int $length = null, int $mode = LOCK_SH): string;

    /**
     * @return string[]
     */
    abstract public function readFileAsLines(): array;

    /**
     * @param int $offset
     * @param int|null $length
     * @param int $mode
     * @return void
     * @throws FileNotFoundException
     */
    abstract public function streamFile(int $offset = 0, ?int $length = null, int $mode = LOCK_SH): void;

    /**
     * write content to storage
     * @param string $content
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
     * @param int $type
     * @return int|null
     */
    abstract public function getTime(int $type = Time::LAST_MODIFIED): ?int;

    /**
     * guess content-type (mime) of file
     * @param bool $withEncoding
     * @return string|null
     */
    abstract public function getFileType(bool $withEncoding = false): ?string;

    /**
     * calculate file-hash
     * @param int $mode Hash::CONTENT | Hash::FILENAME | Hash::FILEPATH
     * @param string $algo hashing-algorithm
     * @param bool $raw
     * @return string|null
     * @throws RuntimeException
     */
    abstract public function getFileHash(int $mode = Hash::CONTENT, string $algo = 'sha256', bool $raw = false): ?string;

    /**
     * @param bool $ifNewOnly
     * @param null|int $time last-modified time
     * @param null|int $atime last-access time
     * @return bool
     * @throws Exception
     */
    abstract public function touch(bool $ifNewOnly = false, ?int $time = null, ?int $atime = null): bool;

    /**
     * access file for binary read/write actions
     * @param int $mode
     * @return Binary
     * @throws UnsupportedException
     */
    public function getHandle(int $mode): Binary
    {
        throw new UnsupportedException(sprintf('Binary access not supported for current "%s" Storage (mode: %d)', get_class($this), $mode), 500);
    }

    /**
     * remove file from filesystem on object destruction
     * => leaving scope or removing object reference
     * @param bool $activate
     * @return self
     */
    public function removeOnFree(bool $activate = true): self
    {
        $this->selfdestruct = $activate;
        return $this;
    }

    /**
     * @param bool $recursive
     * @param int|null $constraints
     * @return Generator list of all files
     * @throws UnsupportedException
     */
    public function list(bool $recursive = false, ?int $constraints = null): Generator
    {
        throw new UnsupportedException(sprintf(
            'Listing Directory-Content is not supported for the current "%s" Storage (recursive: %d, Constraint-bitmask: %b)',
            get_class($this),
            $recursive ? 'true' : 'false',
            $constraints ?? $this->constraints->getConstraints(),
        ), 500);
    }

    /**
     * @param string $mode
     * @return resource
     */
    abstract public function getStream(string $mode = 'rb+');

    /**
     * update content from stream
     * @param resource $stream file-handle
     * @return bool
     */
    abstract public function writeFromStream($stream): bool;

    /**
     * <b>copy</b> file to new destination
     * @param Storage $destination
     * @return bool success
     */
    abstract public function copyFileTo(Storage $destination): bool;

    /**
     * <b>move</b> file to new destination
     * @param Storage $destination
     * @return bool success
     */
    abstract public function moveFileTo(Storage $destination): bool;
}
