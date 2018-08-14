<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage;

use League\Flysystem\Filesystem as FlyFilesystem;
use League\Flysystem\Adapter\AbstractAdapter;
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
    protected $path;

    /**
     * @var array
     */
    protected $metadata;

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
        $this->metadata = $this->flysystem->getMetadata($this->path);
    }

    /**
     * @inheritDoc
     */
    public function getDetails(): array
    {
        return array_merge(parent::getDetails(), [
            'type' => get_class($this->flysystem->getAdapter()),
            'path' => $this->path
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
     */
    public function isFile(): bool
    {
        return $this->flysystem->has($this->path) && $this->metadata['type'] === 'file';
    }

    /**
     * @inheritDoc
     */
    public function isDir(): bool
    {
        return $this->flysystem->has($this->path) && $this->metadata['type'] === 'dir';
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
        return $this->flysystem->has($this->path) && $this->metadata['type'] === 'link';
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
     * @throws UnsupportedException
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
            \fclose($handle);
        }
    }

    /**
     * @inheritDoc
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
            \fclose($handle);
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
            $this->metadata = $this->flysystem->getMetadata($this->path);
            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function removeFile(): bool
    {
        return $this->flysystem->delete($this->path);
    }

    /**
     * @inheritDoc
     */
    public function removeDir(): bool
    {
        if (!$this->isDir()) {
            throw new AccessDeniedException(sprintf('unable to remove directory: "%s"', $this->path), 500);
        }

        if ($this->flysystem->deleteDir($this->path)) {
            $this->metadata = $this->flysystem->getMetadata($this->path);
            return true;
        }

        return false;
    }

    /**
     * @param bool $recursive
     * @return self[] list of all files
     * @throws RuntimeException
     */
    public function list(bool $recursive = false): \Generator
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
     */
    public function getSize(): int
    {
        return $this->flysystem->getSize($this->path);
    }

    /**
     * @inheritDoc
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
     * @throws UnsupportedException
     */
    public function getFileHash(int $mode = Hash::CONTENT, string $algo = 'sha256'): ?string
    {
        if (!$this->isFile()) {
            throw new FileNotFoundException('file not found', 404);
        }

        switch ($mode) {
            case Hash::CONTENT: return hash($algo, $this->readFile(), false);
            case Hash::FILENAME: return hash($algo, $this->path, false);
            default: throw new UnsupportedException('file-hashes are not supported by the default flysystem adapters', 500);
        }
    }

    /**
     * @inheritDoc
     */
    public function getTime(): int
    {
        return $this->flysystem->getTimestamp($this->path);
    }

    /**
     * @inheritDoc
     */
    public function touch(bool $ifNewOnly = false): bool
    {
        try {
            if ($this->flysystem->has($this->path)) {
                return $ifNewOnly ? true : $this->flysystem->write($this->path, '');
            }

            return $this->flysystem->put($this->path, $this->flysystem->read($this->path));
        } finally {
            $this->metadata = $this->flysystem->getMetadata($this->path);
        }
    }

    /**
     * @return bool
     */
    public function mkdir(): bool
    {
        if ($this->flysystem->createDir($this->path)) {
            $this->metadata = $this->flysystem->getMetadata($this->path);
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
}
