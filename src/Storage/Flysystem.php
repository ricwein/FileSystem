<?php

/**
 * @author Richard Weinhold
 */

declare(strict_types=1);

namespace ricwein\FileSystem\Storage;

use Generator;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem as FlyFilesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException as FlySystemException;
use ricwein\FileSystem\Enum\Time;
use ricwein\FileSystem\Exceptions\Exception;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Enum\Hash;
use ricwein\FileSystem\Helper\Stream;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;

/**
 * represents a file/directory at the local filesystem
 */
class Flysystem extends Storage
{
    protected FlyFilesystem $flysystem;
    protected string $path;

    protected string $type;

    /**
     * @param FilesystemAdapter|FlyFilesystem $filesystem
     * @param string $path filename or directory
     * @throws FlySystemException
     * @throws UnexpectedValueException
     */
    public function __construct($filesystem, string $path)
    {
        if ($filesystem instanceof FilesystemAdapter) {
            $this->flysystem = new FlyFilesystem($filesystem);
        } elseif ($filesystem instanceof FlyFilesystem) {
            $this->flysystem = $filesystem;
        } else {
            throw new UnexpectedValueException(sprintf('unable to init Flysystem storage-engine from %s', is_object($filesystem) ? get_class($filesystem) : gettype($filesystem)), 500);
        }

        $this->path = $path;
        $this->type = $this->flysystem->mimeType($path);
    }

    /**
     * @return void
     * @throws AccessDeniedException
     * @throws FlySystemException
     */
    public function __destruct()
    {
        if (!$this->selfdestruct) {
            return;
        }

        if (!$this->flysystem->fileExists($this->path)) {
            return;
        }

        if ($this->isDir()) {
            $this->removeDir();
        } else {
            $this->removeFile();
        }
    }

    /**
     * @return array
     * @throws FlySystemException
     * @throws RuntimeException
     * @throws UnsupportedException
     */
    public function getDetails(): array
    {
        return array_merge(parent::getDetails(), [
            'path' => $this->path,
            'type' => $this->type,
            'timestamp' => $this->getTime(),
            'size' => $this->getSize(),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function doesSatisfyConstraints(): bool
    {
        // @TODO
        return true;
    }

    /**
     * @return string
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * @inheritDoc
     * @throws FlySystemException
     */
    public function isFile(): bool
    {
        if (!$this->flysystem->fileExists($this->path)) {
            return false;
        }
        return !in_array(strtolower($this->type), ['dir', 'directory'], true);
    }

    /**
     * @inheritDoc
     * @return bool
     */
    public function isDir(): bool
    {
        return in_array(strtolower($this->type), ['dir', 'directory'], true);
    }

    /**
     * @inheritDoc
     */
    public function isExecutable(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     * @return bool
     */
    public function isSymlink(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     * @throws FlySystemException
     */
    public function isReadable(): bool
    {
        return $this->flysystem->fileExists($this->path);
    }

    /**
     * @inheritDoc
     */
    public function isWriteable(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     * @param int $offset
     * @param int|null $length
     * @param int $mode
     * @return string
     * @throws FileNotFoundException
     * @throws FlySystemException
     * @throws RuntimeException
     */
    public function readFile(int $offset = 0, ?int $length = null, int $mode = LOCK_SH): string
    {
        if (!$this->isFile()) {
            throw new FileNotFoundException('file not found', 404);
        }

        $handle = $this->flysystem->readStream($this->path);
        if ($handle === false) {
            throw new RuntimeException('error while reading file', 500);
        }

        return (new Stream($handle))->read($offset, $length);
    }

    /**
     * @inheritDoc
     * @return array
     * @throws FileNotFoundException
     * @throws FlySystemException
     * @throws RuntimeException
     */
    public function readFileAsLines(): array
    {
        return explode(PHP_EOL, $this->readFile());
    }

    /**
     * @inheritDoc
     * @param int $offset
     * @param int|null $length
     * @param int $mode
     * @throws FileNotFoundException
     * @throws FlySystemException
     * @throws RuntimeException
     */
    public function streamFile(int $offset = 0, ?int $length = null, int $mode = LOCK_SH): void
    {
        if (!$this->isFile()) {
            throw new FileNotFoundException('file not found', 404);
        }

        $stream = $this->getStream('rb');
        $stream->passthru($offset, $length);
    }

    /**
     * @inheritDoc
     * @throws UnsupportedException
     * @throws FlySystemException
     */
    public function writeFile(string $content, bool $append = false, int $mode = LOCK_EX): bool
    {
        if ($append) {
            throw new UnsupportedException('FlySystem Adapters don\'t support appended writing mode', 500);
        }

        if ($this->flysystem->write($this->path, $content)) {
            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     * @return bool
     * @throws FlySystemException
     */
    public function removeFile(): bool
    {
        $this->flysystem->delete($this->path);
        return true;
    }

    /**
     * @return bool
     * @throws AccessDeniedException
     * @throws FlySystemException
     */
    public function removeDir(): bool
    {
        if (!$this->isDir()) {
            throw new AccessDeniedException(sprintf('unable to remove path, not a directory: "%s"', $this->path), 500);
        }

        $this->flysystem->deleteDirectory($this->path);
        return true;
    }

    /**
     * @inheritDoc
     * @param bool $recursive
     * @return Generator
     * @throws FlySystemException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function list(bool $recursive = false, ?int $constraints = null): Generator
    {
        if (!$this->isDir()) {
            throw new RuntimeException(sprintf('unable to open directory "%s"', $this->path), 500);
        }

        /** @var FileAttributes $file */
        foreach ($this->flysystem->listContents($this->path, $recursive) as $file) {
            yield new self($this->flysystem, sprintf("%s/%s", rtrim($this->path, '/'), basename($file->path())));
        }
    }

    /**
     * @inheritDoc
     * @return int
     * @throws FlySystemException
     */
    public function getSize(): int
    {
        return $this->flysystem->fileSize($this->path);
    }

    /**
     * @inheritDoc
     * @param bool $withEncoding
     * @return string|null
     */
    public function getFileType(bool $withEncoding = false): ?string
    {
        return $this->type;
    }

    /**
     * @inheritDoc
     * @param int $mode
     * @param string $algo
     * @param bool $raw
     * @return string|null
     * @throws FileNotFoundException
     * @throws FlySystemException
     * @throws RuntimeException
     * @throws UnsupportedException
     */
    public function getFileHash(int $mode = Hash::CONTENT, string $algo = 'sha256', bool $raw = false): ?string
    {
        switch ($mode) {
            case Hash::CONTENT:
                if (!$this->isFile()) {
                    throw new FileNotFoundException('file not found', 404);
                }

                return $this->getStream('rb')->closeOnFree()->getHash($algo, $raw);

            case Hash::FILENAME:
                return hash($algo, basename($this->path), $raw);

            case Hash::FILEPATH:
                return hash($algo, $this->path, $raw);

            case Hash::LAST_MODIFIED:
                if (!$this->isFile()) {
                    throw new FileNotFoundException('file not found', 404);
                }
                return hash($algo, $this->getTime(), $raw);
        }
        throw new UnsupportedException("unsupported hash-mode '{$mode}' for flysystem storage", 500);
    }

    /**
     * @inheritDoc
     * @param int $type
     * @return int
     * @throws FlySystemException
     * @throws RuntimeException
     * @throws UnsupportedException
     */
    public function getTime(int $type = Time::LAST_MODIFIED): int
    {
        if ($type !== Time::LAST_MODIFIED) {
            throw new UnsupportedException('Unable to fetch timestamp from Flysystem adapter. Only LAST_MODIFIED is currently supported.', 500);
        }
        if (false !== $timestamp = $this->flysystem->lastModified($this->path)) {
            return $timestamp;
        }

        throw new RuntimeException('unable to fetch timestamp', 500);
    }

    /**
     * @inheritDoc
     * @param bool $ifNewOnly
     * @param int|null $time
     * @param int|null $atime
     * @return bool
     * @throws FlySystemException
     */
    public function touch(bool $ifNewOnly = false, ?int $time = null, ?int $atime = null): bool
    {
        $isFile = $this->isFile();

        if ($ifNewOnly && $isFile) {
            return true;
        }

        if (!$isFile) {
            $this->flysystem->write($this->path, '');
            return true;
        }

        $this->flysystem->move($this->path, $this->path);
        return true;
    }

    /**
     * @return bool
     * @throws FlySystemException
     */
    public function mkdir(): bool
    {
        $this->flysystem->createDirectory($this->path);
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isDotfile(): bool
    {
        return strpos($this->path, '.') === 0;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return sprintf('%s/[Adapter: %s] at: "%s"', parent::__toString(), get_class($this->flysystem), $this->path);
    }


    /**
     * @inheritDoc
     * @throws FlySystemException
     * @throws RuntimeException
     */
    public function getStream(string $mode = 'rb+'): Stream
    {
        $stream = $this->flysystem->readStream($this->path);

        if ($stream === false || !is_resource($stream)) {
            throw new RuntimeException('failed to open stream', 500);
        }

        return new Stream($stream);
    }

    /**
     * @inheritDoc
     * @throws Exception
     * @throws FlySystemException
     */
    public function writeFromStream(Stream $stream): bool
    {
        $this->touch(true);
        $this->flysystem->writeStream($this->path, $stream->getHandle());
        return true;
    }

    /**
     * @inheritDoc
     * @param Storage $destination
     * @return bool
     * @throws FileNotFoundException
     * @throws FlySystemException
     * @throws RuntimeException
     */
    public function copyFileTo(Storage $destination): bool
    {
        switch (true) {

            case $destination instanceof Stream:
            case $destination instanceof Disk:
                $readStream = $this->getStream('rb');

                if (!$destination->writeFromStream($readStream)) {
                    return false;
                }

                $destination->path()->reload();
                return true;

            case $destination instanceof self:
                $this->flysystem->copy($this->path, $destination->path());
                return true;

            case $destination instanceof Memory:
            default:
                return $destination->writeFile($this->readFile());
        }
    }

    /**
     * @inheritDoc
     * @param Storage $destination
     * @return bool
     * @throws FileNotFoundException
     * @throws FlySystemException
     * @throws RuntimeException
     */
    public function moveFileTo(Storage $destination): bool
    {
        switch (true) {

            case $destination instanceof self:
                $this->flysystem->move($this->path, $destination->path());
                return true;

            case $destination instanceof Disk:
            case $destination instanceof Memory:
            default:
                if (!$this->copyFileTo($destination)) {
                    return false;
                }
                $this->removeFile();
                return true;
        }
    }
}
