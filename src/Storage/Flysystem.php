<?php

/**
 * @author Richard Weinhold
 */

namespace ricwein\FileSystem\Storage;

use Generator;
use League\Flysystem\FileExistsException;
use League\Flysystem\Filesystem as FlyFilesystem;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\FileNotFoundException as FlySystemFileNotFoundException;
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
    /**
     * @var FlyFilesystem
     */
    protected $flysystem;

    /**
     * @var string
     */
    protected string $path;

    /**
     * @var array|null
     */
    protected ?array $metadata = null;

    /**
     * @param AbstractAdapter|FlyFilesystem $filesystem
     * @param string $path filename or directory
     * @throws UnexpectedValueException
     */
    public function __construct($filesystem, string $path)
    {
        if ($filesystem instanceof AbstractAdapter) {
            $this->flysystem = new FlyFilesystem($filesystem);
        } elseif ($filesystem instanceof FlyFilesystem) {
            $this->flysystem = $filesystem;
        } else {
            throw new UnexpectedValueException(sprintf('unable to init Flysystem storage-engine from %s', is_object($filesystem) ? get_class($filesystem) : gettype($filesystem)), 500);
        }

        $this->path = $path;
    }

    /**
     * @return array
     * @throws FlySystemFileNotFoundException
     */
    public function getMetadata(): array
    {
        if ($this->metadata === null) {
            if (false !== $metadata = $this->flysystem->getMetadata($this->path)) {
                $this->metadata = $metadata;
            } else {

                // something went terrible wrong...
                return ['type' => null];
            }
        }

        return (array)$this->metadata;
    }

    /**
     * @return void
     * @throws FlySystemFileNotFoundException
     * @throws AccessDeniedException
     */
    public function __destruct()
    {
        if (!$this->selfdestruct) {
            return;
        }

        if (!$this->flysystem->has($this->path)) {
            return;
        }

        if ($this->getMetadata()['type'] === 'file') {
            $this->removeFile();
        } elseif ($this->getMetadata()['type'] === 'dir') {
            $this->removeDir();
        }
    }

    /**
     * @inheritDoc
     * @throws FlySystemFileNotFoundException
     */
    public function getDetails(): array
    {
        return array_merge(parent::getDetails(), [
            'type' => get_class($this->flysystem->getAdapter()),
            'path' => $this->path,
            'metadata' => $this->getMetadata(),
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
     * @throws FlySystemFileNotFoundException
     */
    public function isFile(): bool
    {
        return $this->flysystem->has($this->path) && $this->getMetadata()['type'] === 'file';
    }

    /**
     * @inheritDoc
     * @throws FlySystemFileNotFoundException
     */
    public function isDir(): bool
    {
        return $this->flysystem->has($this->path) && $this->getMetadata()['type'] === 'dir';
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
     * @throws FlySystemFileNotFoundException
     */
    public function isSymlink(): bool
    {
        return $this->flysystem->has($this->path) && $this->getMetadata()['type'] === 'link';
    }

    /**
     * @inheritDoc
     */
    public function isReadable(): bool
    {
        return $this->flysystem->has($this->path);
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
     * @param int|null $offset
     * @param int|null $length
     * @param int $mode
     * @return string
     * @throws FileNotFoundException
     * @throws FlySystemFileNotFoundException
     * @throws RuntimeException
     */
    public function readFile(?int $offset = null, ?int $length = null, int $mode = LOCK_SH): string
    {
        if (!$this->isFile()) {
            throw new FileNotFoundException('file not found', 404);
        }

        try {
            $handle = $this->flysystem->readStream($this->path);
            if ($handle === false) {
                throw new RuntimeException('error while reading file', 500);
            }

            return (new Stream($handle))->read($offset, $length);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @inheritDoc
     * @throws FileNotFoundException
     * @throws FlySystemFileNotFoundException
     * @throws RuntimeException
     */
    public function readFileAsLines(): array
    {
        return explode(PHP_EOL, $this->readFile());
    }

    /**
     * @inheritDoc
     * @param int|null $offset
     * @param int|null $length
     * @param int $mode
     * @throws FileNotFoundException
     * @throws FlySystemFileNotFoundException
     * @throws RuntimeException
     */
    public function streamFile(?int $offset = null, ?int $length = null, int $mode = LOCK_SH): void
    {
        if (!$this->isFile()) {
            throw new FileNotFoundException('file not found', 404);
        }

        try {
            $handle = $this->flysystem->readStream($this->path);
            if ($handle === false) {
                throw new RuntimeException('error while reading file', 500);
            }

            (new Stream($handle))->send($offset, $length);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @inheritDoc
     * @throws UnsupportedException
     */
    public function writeFile(string $content, bool $append = false, int $mode = LOCK_EX): bool
    {
        if ($append) {
            throw new UnsupportedException('FlySystem Adapters don\'t support appended writing mode', 500);
        }

        if ($this->flysystem->put($this->path, $content)) {
            $this->metadata = null;
            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     * @throws FlySystemFileNotFoundException
     */
    public function removeFile(): bool
    {
        return $this->flysystem->delete($this->path);
    }

    /**
     * @inheritDoc
     * @return bool
     * @throws AccessDeniedException
     * @throws FlySystemFileNotFoundException
     */
    public function removeDir(): bool
    {
        if (!$this->isDir()) {
            throw new AccessDeniedException(sprintf('unable to remove path, not a directory: "%s"', $this->path), 500);
        }

        if (!$this->flysystem->deleteDir($this->path)) {
            return false;
        }

        $this->metadata = null;
        return true;
    }

    /**
     * @inheritDoc
     * @param bool $recursive
     * @return Generator
     * @throws FlySystemFileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function list(bool $recursive = false): Generator
    {
        if (!$this->isDir()) {
            throw new RuntimeException(sprintf('unable to open directory "%s"', $this->path), 500);
        }

        foreach ($this->flysystem->listContents($this->path, $recursive) as $file) {
            yield new self($this->flysystem, rtrim($this->path, '/') . '/' . $file['basename']);
        }
    }

    /**
     * @inheritDoc
     * @throws FlySystemFileNotFoundException
     */
    public function getSize(): int
    {
        return $this->flysystem->getSize($this->path);
    }

    /**
     * @inheritDoc
     * @throws FlySystemFileNotFoundException
     */
    public function getFileType(bool $withEncoding = false): ?string
    {
        if (false !== $type = $this->flysystem->getMimetype($this->path)) {
            return $type;
        }

        return null;
    }

    /**
     * @inheritDoc
     * @param int $mode
     * @param string $algo
     * @return string|null
     * @throws FileNotFoundException
     * @throws FlySystemFileNotFoundException
     * @throws RuntimeException
     * @throws UnsupportedException
     */
    public function getFileHash(int $mode = Hash::CONTENT, string $algo = 'sha256'): ?string
    {
        if (!$this->isFile()) {
            throw new FileNotFoundException('file not found', 404);
        }

        switch ($mode) {
            case Hash::CONTENT:
                $stream = $this->getStream();
                return (new Stream($stream))->closeOnFree()->hash($algo);
            case Hash::FILENAME:
                return hash($algo, $this->path, false);
            case Hash::LAST_MODIFIED:
                return hash($algo, $this->getTime(Time::LAST_MODIFIED), false);
            default:
                throw new UnsupportedException('filepath-hashes are not supported by flysystem adapters', 500);
        }
    }

    /**
     * @inheritDoc
     * @return int
     * @throws FlySystemFileNotFoundException
     * @throws RuntimeException
     * @throws UnsupportedException
     */
    public function getTime(int $type = Time::LAST_MODIFIED): int
    {
        if ($type !== Time::LAST_MODIFIED) {
            throw new UnsupportedException('Unable to fetch timestamp from Flysystem adapter. Only LAST_MODIFIED is currently supported.', 500);
        }
        if (false !== $timestamp = $this->flysystem->getTimestamp($this->path)) {
            return (int)$timestamp;
        }

        throw new RuntimeException('unable to fetch timestamp', 500);
    }

    /**
     * @inheritDoc
     * @param bool $ifNewOnly
     * @param int|null $time
     * @param int|null $atime
     * @return bool
     * @throws FlySystemFileNotFoundException
     * @throws FileExistsException
     */
    public function touch(bool $ifNewOnly = false, ?int $time = null, ?int $atime = null): bool
    {
        $isFile = $this->isFile();

        if ($ifNewOnly && $isFile) {
            return true;
        }

        try {
            if (!$isFile) {
                return $this->flysystem->write($this->path, '');
            }

            return $this->flysystem->rename($this->path, $this->path);
        } finally {
            $this->metadata = null;
        }
    }

    /**
     * @return bool
     */
    public function mkdir(): bool
    {
        if ($this->flysystem->createDir($this->path)) {
            $this->metadata = null;
            return true;
        }

        return false;
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
        return sprintf('%s/[Adapter: %s] at: "%s"', parent::__toString(), get_class($this->flysystem->getAdapter()), $this->path);
    }


    /**
     * @inheritDoc
     * @param string $mode
     * @return resource
     * @throws FlySystemFileNotFoundException
     * @throws RuntimeException
     */
    public function getStream(string $mode = 'r+')
    {
        $stream = $this->flysystem->readStream($this->path);

        if ($stream === false || !is_resource($stream)) {
            throw new RuntimeException('failed to open stream', 500);
        }

        return $stream;
    }

    /**
     * @inheritDoc
     * @param $stream
     * @return bool
     * @throws FileExistsException
     * @throws FlySystemFileNotFoundException
     * @throws Exception
     */
    public function writeFromStream($stream): bool
    {
        $this->touch(true);
        return $this->flysystem->updateStream($this->path, $stream);
    }

    /**
     * @inheritDoc
     * @param Storage $destination
     * @return bool
     * @throws FileExistsException
     * @throws FileNotFoundException
     * @throws FlySystemFileNotFoundException
     * @throws RuntimeException
     */
    public function copyFileTo(Storage $destination): bool
    {
        switch (true) {

            case $destination instanceof Disk:
                $readStream = $this->getStream('r');
                try {
                    if (!$destination->writeFromStream($readStream)) {
                        return false;
                    }
                    $destination->path()->reload();
                    return true;
                } finally {
                    fclose($readStream);
                }

            case $destination instanceof Flysystem:
                return $this->flysystem->copy($this->path, $destination->path());

            case $destination instanceof Memory:
            default:
                return $destination->writeFile($this->readFile());
        }
    }

    /**
     * @inheritDoc
     * @param Storage $destination
     * @return bool
     * @throws FileExistsException
     * @throws FileNotFoundException
     * @throws FlySystemFileNotFoundException
     * @throws RuntimeException
     */
    public function moveFileTo(Storage $destination): bool
    {
        switch (true) {

            case $destination instanceof Flysystem:
                return $this->flysystem->rename($this->path, $destination->path());

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
