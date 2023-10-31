<?php
declare(strict_types=1);

namespace ricwein\FileSystem\Storage;

use Generator;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem as FlyFilesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException as FlySystemException;
use League\Flysystem\Visibility;
use ricwein\FileSystem\Enum\Hash;
use ricwein\FileSystem\Enum\Time;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\Helper\Stream as StreamResource;
use ricwein\FileSystem\Path;
use ricwein\FileSystem\Storage\Extensions\Binary;

/**
 * represents a file/directory at the local filesystem
 */
class Flysystem extends BaseStorage implements FileStorageInterface, DirectoryStorageInterface
{
    protected FlyFilesystem $flysystem;
    protected Path $path;

    protected string $type;

    /**
     * @throws FlySystemException
     */
    public function __construct(FlyFilesystem|FilesystemAdapter $filesystem, string $path)
    {
        if ($filesystem instanceof FlyFilesystem) {
            $this->flysystem = $filesystem;
        } else {
            $this->flysystem = new FlyFilesystem($filesystem);
        }

        $this->path = new Path($path);

        if ($this->flysystem->fileExists($path)) {
            $this->type = $this->flysystem->mimeType($path);
        }
    }

    /**
     * @internal
     */
    public function getFlySystem(): FlyFilesystem
    {
        return $this->flysystem;
    }

    /**
     * @throws AccessDeniedException
     * @throws FlySystemException
     */
    public function __destruct()
    {
        if (!$this->selfDestruct) {
            return;
        }

        if (!$this->flysystem->fileExists($this->path->getRawPath())) {
            return;
        }

        if ($this->isDir()) {
            $this->removeDir();
        } else {
            $this->removeFile();
        }
    }

    /**
     * @throws FlySystemException
     * @throws RuntimeException
     * @throws UnsupportedException
     */
    public function getDetails(): array
    {
        return array_merge(parent::getDetails(), ['path' => $this->path->getRawPath(), 'type' => $this->type, 'timestamp' => $this->getTime(), 'size' => $this->getSize(),]);
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
     * @inheritDoc
     * @throws FlySystemException
     */
    public function isFile(): bool
    {
        if (!$this->flysystem->fileExists($this->path->getRawPath())) {
            return false;
        }
        return !in_array(strtolower($this->type), ['dir', 'directory'], true);
    }

    /**
     * @inheritDoc
     * @throws FlySystemException
     */
    public function isDir(): bool
    {
        return $this->flysystem->directoryExists($this->path->getRawPath());
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
        return $this->flysystem->fileExists($this->path->getRawPath());
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
     * @throws FileNotFoundException
     * @throws FlySystemException
     * @throws RuntimeException
     */
    public function readFile(int $offset = 0, ?int $length = null, int $mode = LOCK_SH): string
    {
        if (!$this->isFile()) {
            throw new FileNotFoundException('file not found', 404);
        }

        $handle = $this->flysystem->readStream($this->path->getRawPath());
        if ($handle === false) {
            throw new RuntimeException('error while reading file', 500);
        }

        return (new StreamResource($handle))->read($offset, $length);
    }

    /**
     * @inheritDoc
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

        $this->flysystem->write($this->path->getRawPath(), $content);
        return true;
    }

    /**
     * @inheritDoc
     * @throws FlySystemException
     */
    public function removeFile(): bool
    {
        $this->flysystem->delete($this->path->getRawPath());
        return true;
    }

    /**
     * @throws AccessDeniedException
     * @throws FlySystemException
     */
    public function removeDir(): bool
    {
        if (!$this->isDir()) {
            throw new AccessDeniedException(sprintf('unable to remove path, not a directory: "%s"', $this->path->getRawPath()), 500);
        }

        $this->flysystem->deleteDirectory($this->path->getRawPath());
        return true;
    }

    /**
     * @inheritDoc
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
        foreach ($this->flysystem->listContents($this->path->getRawPath(), $recursive) as $file) {
            yield new self($this->flysystem, sprintf("%s/%s", rtrim($this->path->getRawPath(), '/'), basename($file->path())));
        }
    }

    /**
     * @inheritDoc
     * @throws FlySystemException
     */
    public function getSize(): int
    {
        return $this->flysystem->fileSize($this->path->getRawPath());
    }

    /**
     * @inheritDoc
     */
    public function getFileType(bool $withEncoding = false): ?string
    {
        return $this->type;
    }

    /**
     * @inheritDoc
     * @throws FileNotFoundException
     * @throws FlySystemException
     * @throws RuntimeException
     * @throws UnsupportedException
     */
    public function getFileHash(Hash $mode = Hash::CONTENT, string $algo = 'sha256', bool $raw = false): ?string
    {
        return match ($mode) {
            Hash::FILENAME => hash($algo, basename($this->path->getRawPath()), $raw),
            Hash::FILEPATH => hash($algo, $this->path->getRawPath(), $raw),
            Hash::CONTENT => $this->isFile() ? $this->getStream('rb')->closeOnFree()->getHash($algo, $raw) : throw new FileNotFoundException('file not found', 404),
            Hash::LAST_MODIFIED => $this->isFile() ? hash($algo, (string)$this->getTime(), $raw) : throw new FileNotFoundException('file not found', 404),

        };
    }

    /**
     * @inheritDoc
     * @throws FlySystemException
     * @throws RuntimeException
     * @throws UnsupportedException
     */
    public function getTime(Time $type = Time::LAST_MODIFIED): int
    {
        if ($type !== Time::LAST_MODIFIED) {
            throw new UnsupportedException('Unable to fetch timestamp from Flysystem adapter. Only LAST_MODIFIED is currently supported.', 500);
        }

        $timestamp = $this->flysystem->lastModified($this->path->getRawPath());

        if ($timestamp) {
            return $timestamp;
        }

        if (null !== $time = $timestamp->lastModified()) {
            return $time;
        }

        throw new RuntimeException('unable to fetch timestamp', 500);
    }

    /**
     * @inheritDoc
     * @throws FlySystemException
     */
    public function touch(bool $ifNewOnly = false, ?int $time = null, ?int $atime = null): bool
    {
        $isFile = $this->isFile();

        if ($ifNewOnly && $isFile) {
            return true;
        }

        if (!$isFile) {
            $this->flysystem->write($this->path->getRawPath(), '');
            return true;
        }

        $this->flysystem->move($this->path->getRawPath(), $this->path->getRawPath());
        return true;
    }

    /**
     * @throws FlySystemException
     */
    public function mkdir(bool $ifNewOnly = false, int $permissions = 0755): bool
    {
        $this->flysystem->createDirectory($this->path->getRawPath(), [Config::OPTION_DIRECTORY_VISIBILITY => $permissions > 700 ? Visibility::PUBLIC : Visibility::PRIVATE,]);
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isDotfile(): bool
    {
        return str_starts_with($this->path->getRawPath(), '.');
    }

    public function __toString(): string
    {
        return sprintf('%s/[Adapter: %s] at: "%s"', parent::__toString(), get_class($this->flysystem), $this->path);
    }


    /**
     * {@inheritDoc}
     * @throws FlySystemException
     * @throws RuntimeException
     */
    public function getStream(string $mode = 'rb+'): StreamResource
    {
        $stream = $this->flysystem->readStream($this->path->getRawPath());

        if ($stream === false || !is_resource($stream)) {
            throw new RuntimeException('failed to open stream', 500);
        }

        return new StreamResource($stream);
    }

    /**
     * @inheritDoc
     * @throws FlySystemException
     */
    public function writeFromStream(StreamResource $stream): bool
    {
        $this->touch(true);
        $this->flysystem->writeStream($this->path->getRawPath(), $stream->getHandle());
        return true;
    }

    /**
     * @inheritDoc
     * @throws FileNotFoundException
     * @throws FlySystemException
     * @throws RuntimeException
     */
    public function copyFileTo(BaseStorage $destination): bool
    {
        switch (true) {

            case $destination instanceof Stream:
            case $destination instanceof Disk:
                $readStream = $this->getStream('rb');

                if (!$destination->writeFromStream($readStream)) {
                    return false;
                }

                $destination->getPath()->reload();
                return true;

            case $destination instanceof self:
                $this->flysystem->copy($this->path->getRawPath(), $destination->getPath()->getRawPath());
                return true;

            case $destination instanceof Memory:
            default:
                return $destination->writeFile($this->readFile());
        }
    }

    /**
     * @inheritDoc
     * @throws FileNotFoundException
     * @throws FlySystemException
     * @throws RuntimeException
     */
    public function moveFileTo(BaseStorage $destination): bool
    {
        switch (true) {

            case $destination instanceof self:
                $this->flysystem->move($this->path->getRawPath(), $destination->getPath()->getRawPath());
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


    /**
     * changes current directory
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @internal
     */
    public function cd(array $path): void
    {
        $this->path = new Path(sprintf("%s%s", $this->path->getRawPath(), implode('/', $path)));
    }

    public function getHandle(int $mode): Binary
    {
        throw new UnsupportedException(sprintf('%s::%s() is not supported.', static::class, __METHOD__), 500);
    }

    public function __serialize(): array
    {
        throw new UnsupportedException("Unable to serialize FlySystem Storage.", 500);
    }
}
