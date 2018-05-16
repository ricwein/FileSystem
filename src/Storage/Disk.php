<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage;

use ricwein\FileSystem\FileSystem;
use ricwein\FileSystem\Helper\Hash;
use ricwein\FileSystem\Exception\Exception;
use ricwein\FileSystem\Exception\FileNotFoundException;
use ricwein\FileSystem\Exception\RuntimeException;
use ricwein\FileSystem\Helper\Path;
use ricwein\FileSystem\Helper\MimeType;

/**
 * represents a file/directory at the local filesystem
 */
class Disk extends Storage
{
    /**
     * @var Path|null
     */
    protected $path;

    /**
     * @param string|FileSystem|Path $path ,...
     */
    public function __construct(... $path)
    {
        $this->path = new Path($path);
    }

    public function getDetails(): array
    {
        return array_merge(parent::getDetails(), [
            'path' => $this->path->getDetails(),
        ]);
    }

    /**
     * @return Path
     */
    public function path(): Path
    {
        return $this->path;
    }

    /**
     * @inheritDoc
     */
    public function isFile(): bool
    {
        return $this->path->real !== null && file_exists($this->path->real) && is_file($this->path->real);
    }

    /**
     * @inheritDoc
     */
    public function isDirectory():bool
    {
        return $this->path->real !== null && is_dir($this->path->real);
    }

    /**
     * @inheritDoc
     */
    public function isExecutable(): bool
    {
        return $this->isFile() && is_executable($this->path->real);
    }

    /**
     * @inheritDoc
     */
    public function isSymlink(): bool
    {
        return $this->path->real !== null && is_link($this->path->real);
    }

    /**
     * @inheritDoc
     */
    public function isReadable(): bool
    {
        return $this->path->real !== null && is_readable($this->path->real);
    }

    /**
     * @inheritDoc
     */
    public function isWriteable(): bool
    {
        return $this->path->real !== null && is_writable($this->path->real);
    }

    /**
     * @inheritDoc
     * @throws FileNotFoundException|RuntimeException
     */
    public function readFile(?int $offset = null, ?int $length = null, int $mode = LOCK_SH): string
    {
        if (!$this->isFile()) {
            throw new FileNotFoundException('file not found', 404);
        }

        // open file-handler in readonly mode
        $handle = fopen($this->path->real, 'r');

        try {

            // try to set lock if provided
            if ($mode !== 0 && !flock($handle, $mode)) {
                throw new RuntimeException('unable to lock file', 500);
            }

            // read whole file
            if ($offset === null || $length === null) {
                return fread($handle, filesize($this->path->real));
            }

            // read part of file
            fseek($handle, $offset);
            return fread($handle, $length);
        } finally {

            // ensure the file in unlocked after reading and the file-handler is closed again
            if ($mode !== 0) {
                flock($handle, LOCK_UN);
            }
            fclose($handle);
        }
    }

    /**
     * @inheritDoc
     */
    public function writeFile(string $content, int $mode = 0): bool
    {
        return file_put_contents($this->path->real ?? $this->path->raw, $content, $mode) !== false;
    }

    /**
     * @inheritDoc
     */
    public function removeFile(): bool
    {
        return unlink($this->path->real ?? $this->path->raw);
    }

    /**
     * @inheritDoc
     */
    public function getFileSize(): ?int
    {
        if ($this->path->real !== null && false !== $filesize = filesize($this->path->real)) {
            return $filesize;
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getFileType(bool $withEncoding = false): ?string
    {
        if ($this->path->real === null) {
            return null;
        }

        if ('text/plain' !== $type = (new \finfo($withEncoding ? FILEINFO_MIME : FILEINFO_MIME_TYPE))->file($this->path->raw)) {
            return $type;
        }

        if (array_key_exists($this->path->extension, MimeType::EXTENSION_MAP)) {
            return MimeType::EXTENSION_MAP[$this->path->extension];
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function getFileHash(int $mode = Hash::CONTENT, string $algo = 'sha256'): ?string
    {
        if ($this->path->real === null) {
            return null;
        }

        switch ($mode) {
            case Hash::CONTENT: return hash_file($algo, $this->path->real, false);
            case Hash::FILENAME: return hash($algo, $this->path->basename, false);
            case Hash::FILEPATH: return hash($algo, $this->path->real, false);
            default: throw new RuntimeException('unknown hashing-mode', 500);
        }
    }

    /**
     * @inheritDoc
     */
    public function touch(bool $ifNewOnly = false): Storage
    {
        if ($ifNewOnly === true && $this->isFile()) {
            return true;
        }

        // actual touch file
        if (!touch($this->path->raw)) {
            throw new Exception('unable to touch file', 500);
        }

        // reset internal path-state to re-evaluate the realpath
        $this->path->reload();

        return $this;
    }
}
