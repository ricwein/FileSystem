<?php
declare(strict_types=1);

namespace ricwein\FileSystem\Storage;

use finfo;
use League\Flysystem\FilesystemException;
use ricwein\FileSystem\Enum\Hash;
use ricwein\FileSystem\Enum\Time;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\Helper\MimeType;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Helper\Stream as StreamResource;

class Stream extends Storage
{
    private StreamResource $stream;

    protected int $lastModified;
    protected int $lastAccessed;
    protected int $created;

    /**
     * Stream constructor.
     * @param StreamResource|resource|string $stream
     * @throws RuntimeException
     * @throws UnsupportedException
     */
    public function __construct(mixed $stream, private bool $lockOnIO = true)
    {
        if ($stream instanceof StreamResource) {
            $this->stream = $stream;
        } elseif (is_resource($stream)) {
            $this->stream = new StreamResource($stream);
        } elseif (is_string($stream)) {
            $this->stream = StreamResource::fromResourceName($stream);
        } else {
            throw new UnsupportedException(sprintf('The Stream storage only supports Helper\Stream, resource or string inputs, but %s was given instead.', get_debug_type($stream)), 500);
        }

        $now = time();
        $this->lastModified = $now;
        $this->lastAccessed = $now;
        $this->created = $now;
    }

    /**
     * @inheritDoc
     */
    public function doesSatisfyConstraints(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isFile(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isDir(): bool
    {
        return false;
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
     */
    public function isReadable(): bool
    {
        return $this->stream->isReadable();
    }

    /**
     * @inheritDoc
     */
    public function isWriteable(): bool
    {
        return $this->stream->isWriteable();
    }

    /**
     * @inheritDoc
     */
    public function isDotfile(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     * @throws FileNotFoundException
     * @throws RuntimeException
     */
    public function readFile(int $offset = 0, ?int $length = null, int $mode = LOCK_SH): string
    {
        $this->lastAccessed = time();

        if (!$this->stream->isReadable()) {
            throw new FileNotFoundException('File not found', 404);
        }

        if ($this->lockOnIO) {
            $this->stream->lock($mode);
        }
        try {
            return $this->stream->read($offset, $length);
        } finally {
            if ($this->lockOnIO) {
                $this->stream->unlock();
            }
        }
    }

    /**
     * @inheritDoc
     * @throws FileNotFoundException
     * @throws RuntimeException
     */
    public function readFileAsLines(): array
    {
        $this->lastAccessed = time();

        if (!$this->stream->isReadable()) {
            throw new FileNotFoundException('File not found', 404);
        }

        return explode(PHP_EOL, $this->readFile());
    }

    /**
     * @inheritDoc
     * @throws FileNotFoundException
     * @throws RuntimeException
     */
    public function streamFile(int $offset = 0, ?int $length = null, int $mode = LOCK_SH): void
    {
        $this->lastAccessed = time();

        if (!$this->stream->isReadable()) {
            throw new FileNotFoundException('File not found', 404);
        }

        $output = StreamResource::fromResourceName('php://output', 'wb');

        if ($this->lockOnIO) {
            $this->stream->lock($mode);
        }
        try {
            $this->stream->copyToStream($output, $offset, $length);
        } finally {
            if ($this->lockOnIO) {
                $this->stream->unlock();
            }
        }
    }

    /**
     * @inheritDoc
     * @throws FileNotFoundException
     * @throws RuntimeException
     */
    public function writeFile(string $content, bool $append = false, int $mode = 0): bool
    {
        $this->lastModified = time();

        if (!$this->stream->isWriteable()) {
            throw new FileNotFoundException('File not writeable', 400);
        }

        if ($this->lockOnIO) {
            $this->stream->lock($mode);
        }

        try {

            if (!$append) {
                $this->stream->rewind();
            }

            $this->stream->write($content);
            return true;

        } finally {
            if ($this->lockOnIO) {
                $this->stream->unlock();
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function removeFile(): bool
    {
        $uri = $this->stream->getAttribute('uri');
        if (is_string($uri) && str_starts_with($uri, '/') && is_file($uri)) {
            $this->stream->forceClose();
            unlink($uri);
        } else {
            $this->stream->closeOnFree();
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getSize(): int
    {
        return $this->stream->getSize();
    }

    /**
     * @inheritDoc
     */
    public function getTime(int $type = Time::LAST_MODIFIED): ?int
    {
        switch ($type) {
            case Time::LAST_MODIFIED:
                return $this->lastModified;
            case Time::LAST_ACCESSED:
                return $this->lastAccessed;
            case Time::CREATED:
                return $this->created;
        }

        return null;
    }

    /**
     * @inheritDoc
     * @throws FileNotFoundException
     * @throws RuntimeException
     */
    public function getFileType(bool $withEncoding = false): ?string
    {
        $uri = $this->stream->getAttribute('uri');
        if (is_string($uri) && false !== $pos = strrchr($uri, '.')) {
            $extension = substr($pos, 1);
            return MimeType::getMimeFor($extension);
        }

        $content = $this->readFile();
        return (new finfo($withEncoding ? FILEINFO_MIME : FILEINFO_MIME_TYPE))->buffer($content ?? '');
    }

    /**
     * @inheritDoc
     * @throws RuntimeException
     */
    public function getFileHash(int $mode = Hash::CONTENT, string $algo = 'sha256', bool $raw = false): ?string
    {
        switch ($mode) {
            case Hash::CONTENT:
                return $this->stream->getHash($algo, $raw);
            case Hash::LAST_MODIFIED:
                return hash($algo, (string)$this->lastModified, $raw);
            case Hash::FILENAME:
            case Hash::FILEPATH:
                throw new RuntimeException('Unable to calculate filepath/name hash for stream.', 500);
            default:
                throw new RuntimeException('Unknown hashing-mode.', 500);
        }
    }

    /**
     * @inheritDoc
     */
    public function touch(bool $ifNewOnly = false, ?int $time = null, ?int $atime = null): bool
    {
        $this->lastModified = $time ?? time();

        if ($atime !== null) {
            $this->lastAccessed = $atime;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getStream(string $mode = 'rb+'): StreamResource
    {
        return $this->stream;
    }

    /**
     * @inheritDoc
     * @throws RuntimeException
     */
    public function writeFromStream(StreamResource $stream): bool
    {
        $stream->copyToStream($this->stream);
        return true;
    }

    /**
     * @inheritDoc
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnsupportedException
     * @throws FilesystemException
     */
    public function copyFileTo(Storage $destination): bool
    {
        switch (true) {
            case $destination instanceof Memory:
            case $destination instanceof Flysystem:
                $destination->writeFile($this->readFile());
                return true;

            default:
                $this->stream->copyToStream($destination->getStream('wb'));
                return true;
        }
    }

    /**
     * @inheritDoc
     * @throws FileNotFoundException
     * @throws FilesystemException
     * @throws RuntimeException
     * @throws UnsupportedException
     */
    public function moveFileTo(Storage $destination): bool
    {
        if (!$this->copyFileTo($destination)) {
            return false;
        }

        return $this->removeFile();
    }
}
