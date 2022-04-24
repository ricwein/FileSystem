<?php
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
use ricwein\FileSystem\Helper\Stream;
use ricwein\FileSystem\Storage\Extensions\Binary;

/**
 * base-implementation for all Storage Adapters
 */
abstract class Storage
{
    protected ?Constraint $constraints = null;
    protected Path $path;

    /**
     * remove file from filesystem on object destruction
     * => leaving scope or removing object reference
     */
    protected bool $selfDestruct = false;

    /**
     * returns all detail-information for testing/debugging purposes
     * @internal
     */
    public function getDetails(): array
    {
        return [
            'storage' => static::class,
        ];
    }

    /**
     * @internal
     */
    public function setConstraints(int $constraints): static
    {
        $this->constraints = new Constraint($constraints);
        return $this;
    }

    /**
     * @internal
     */
    public function getConstraints(): int
    {
        return $this->constraints->getConstraints();
    }

    /**
     * @internal
     */
    public function getConstraintViolations(ConstraintsException $previous = null): ?ConstraintsException
    {
        return $this->constraints->getErrors($previous);
    }

    public function __toString(): string
    {
        return sprintf('[Storage: %s]', trim(str_replace(self::class, '', get_class($this)), '\\'));
    }

    public function getPath(): Path
    {
        return $this->path;
    }

    /**
     * check if current path satisfies the given constraints
     * @internal
     */
    abstract public function doesSatisfyConstraints(): bool;

    /**
     * check if file exists and is an actual file
     * @internal
     */
    abstract public function isFile(): bool;

    /**
     * check if path is directory
     * @internal
     */
    abstract public function isDir(): bool;

    /**
     * check if file exists and is executable
     * @internal
     */
    abstract public function isExecutable(): bool;

    /**
     * check if path is a symlink
     * @internal
     */
    abstract public function isSymlink(): bool;

    /**
     * check if path is readable
     * @internal
     */
    abstract public function isReadable(): bool;

    /**
     * check if path is writeable
     * @internal
     */
    abstract public function isWriteable(): bool;

    /**
     * @internal
     */
    abstract public function isDotfile(): bool;

    /**
     * @throws FileNotFoundException
     * @internal
     */
    abstract public function readFile(int $offset = 0, ?int $length = null, int $mode = LOCK_SH): string;

    /**
     * @return string[]
     * @internal
     */
    abstract public function readFileAsLines(): array;

    /**
     * @throws FileNotFoundException
     * @internal
     */
    abstract public function streamFile(int $offset = 0, ?int $length = null, int $mode = LOCK_SH): void;

    /**
     * write content to storage
     * @param int $mode LOCK_EX
     * @internal
     */
    abstract public function writeFile(string $content, bool $append = false, int $mode = 0): bool;

    /**
     * remove file from storage
     * @internal
     */
    abstract public function removeFile(): bool;

    /**
     * size of file from storage
     * @internal
     */
    abstract public function getSize(): int;

    /**
     * get last-modified timestamp
     * @internal
     */
    abstract public function getTime(Time $type = Time::LAST_MODIFIED): ?int;

    /**
     * guess content-type (mime) of file
     * @internal
     */
    abstract public function getFileType(bool $withEncoding = false): ?string;

    /**
     * calculate file-hash
     * @param string $algo hashing-algorithm
     * @throws RuntimeException
     * @internal
     */
    abstract public function getFileHash(Hash $mode = Hash::CONTENT, string $algo = 'sha256', bool $raw = false): ?string;

    /**
     * @param null|int $time last-modified time
     * @param null|int $atime last-access time
     * @throws Exception
     * @internal
     */
    abstract public function touch(bool $ifNewOnly = false, ?int $time = null, ?int $atime = null): bool;

    /**
     * access file for binary read/write actions
     * @throws UnsupportedException
     * @internal
     */
    public function getHandle(int $mode): Binary
    {
        throw new UnsupportedException(sprintf('Binary access not supported for current "%s" Storage (mode: %d)', get_class($this), $mode), 500);
    }

    /**
     * remove file from filesystem on object destruction
     * => leaving scope or removing object reference
     * @internal
     */
    public function removeOnFree(bool $activate = true): static
    {
        $this->selfDestruct = $activate;
        return $this;
    }

    /**
     * @return Generator|static[] list of all files
     * @throws UnsupportedException
     * @noinspection PhpInconsistentReturnPointsInspection
     * @internal
     * @noinspection PhpDocSignatureInspection
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
     * @internal
     */
    abstract public function getStream(string $mode = 'rb+'): Stream;

    /**
     * update content from stream
     * @param Stream $stream file-handle
     * @internal
     */
    abstract public function writeFromStream(Stream $stream): bool;

    /**
     * <b>copy</b> file to new destination
     * @internal
     */
    abstract public function copyFileTo(Storage $destination): bool;

    /**
     * <b>move</b> file to new destination
     * @internal
     */
    abstract public function moveFileTo(Storage $destination): bool;
}
