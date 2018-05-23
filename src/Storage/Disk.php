<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage;

use ricwein\FileSystem\FileSystem;
use ricwein\FileSystem\Helper\Hash;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Helper\Path;
use ricwein\FileSystem\Helper\MimeType;

/**
 * represents a file/directory at the local filesystem
 */
class Disk extends Storage
{
    /**
     * @var Path
     */
    protected $path;

    /**
     * @param string|FileSystem|Path $path ,...
     */
    public function __construct(... $path)
    {
        $this->path = new Path($path);
    }

    /**
     * @inheritDoc
     */
    public function getDetails(): array
    {
        return array_merge(parent::getDetails(), $this->path->getDetails());
    }

    /**
     * @inheritDoc
     */
    public function doesSatisfyConstraints(): bool
    {
        return $this->constraints->isValidPath($this->path);
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
        return $this->path->real !== null && file_exists($this->path->real) && $this->path->fileInfo()->isFile();
    }

    /**
     * @inheritDoc
     */
    public function isDir(): bool
    {
        return $this->path->real !== null && file_exists($this->path->real) && $this->path->fileInfo()->isDir();
    }

    /**
     * @inheritDoc
     */
    public function isExecutable(): bool
    {
        return $this->isFile() && $this->path->fileInfo()->isExecutable();
    }

    /**
     * @inheritDoc
     */
    public function isSymlink(): bool
    {
        return $this->path->real !== null && $this->path->fileInfo()->isLink();
    }

    /**
     * @inheritDoc
     */
    public function isReadable(): bool
    {
        return $this->path->real !== null && $this->path->fileInfo()->isReadable();
    }

    /**
     * @inheritDoc
     */
    public function isWriteable(): bool
    {
        return $this->path->fileInfo()->isWritable();
    }

    /**
     * @inheritDoc
     * @throws FileNotFoundException|RuntimeException
     */
    public function readFile(?int $offset = null, ?int $length = null, int $mode = LOCK_SH): string
    {
        if (!$this->isFile() || !$this->isReadable()) {
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
            if (($offset === null || $length === null)) {
                $filesize = $this->path->fileInfo()->getSize();
                return ($filesize <= 0) ? '' : fread($handle, $filesize);
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
    public function writeFile(string $content, bool $append = false, int $mode = LOCK_EX): bool
    {
        // ensure file exists and path-real is set correctly
        $this->touch(true);

        // open file-handler in readonly mode
        $handle = fopen($this->path->real, $append ? 'a' : 'w');

        try {

            // try to set lock if provided
            if ($mode !== 0 && !flock($handle, $mode)) {
                throw new RuntimeException('unable to lock file', 500);
            }

            // write content
            if (fwrite($handle, $content) <= 0) {
                return false;
            }

            $this->path->reload();
            return true;
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
    public function removeFile(): bool
    {
        return unlink($this->path->real ?? $this->path->raw);
    }


    /**
     * @param bool $recursive
     * @param int $mode
     * @return \Iterator
     */
    protected function getIterator(bool $recursive, int $mode = \RecursiveIteratorIterator::SELF_FIRST): \Iterator
    {
        if (!$recursive) {
            return new \DirectoryIterator($this->path->real);
        }

        return new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->path->real, \RecursiveDirectoryIterator::SKIP_DOTS),
            $mode
        );
    }


    /**
     * removes directory from disk
     * @return bool
     * @throws AccessDeniedException
     */
    public function removeDir(): bool
    {
        if (!$this->isDir()) {
            throw new AccessDeniedException(sprintf('unable to remove non-existing file for path: "%s"', $this->path->raw), 500);
        }

        try {
            $iterator = $this->getIterator(true, \RecursiveIteratorIterator::CHILD_FIRST);

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {

                // file not readable
                if (!$file->isReadable()) {
                    throw new AccessDeniedException(sprintf('unable to access file for path: "%s"', $file->getPathname()), 500);
                }

                // try to remove files/dirs/links
                switch ($file->getType()) {
                    case 'dir': rmdir($file->getRealPath()); break;
                    case 'link': unlink($file->getPathname()); break;
                    default: unlink($file->getRealPath());
                }
            }

            return rmdir($this->path->raw);
        } finally {
            $this->path->reload();
        }
    }

    /**
     * @param bool $recursive
     * @return self[] list of all file-paths
     * @throws RuntimeException
     */
    public function list(bool $recursive = false): \Generator
    {
        if (!$this->isDir()) {
            throw new RuntimeException(sprintf('unable to open directory "%s"', $this->path->raw), 500);
        }

        $iterator = $this->getIterator($recursive);

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {

            // file not readable
            if (!$file->isReadable()) {
                throw new AccessDeniedException(sprintf('unable to access file for path: "%s"', $file->getPathname()), 500);
            }

            $pathBase = dirname(realpath($this->path->safepath));
            yield new self($pathBase, str_replace($pathBase, '', $file->getRealPath()));
        }
    }

    /**
     * @inheritDoc
     */
    public function getSize(): int
    {
        return $this->path->fileInfo()->getSize();
    }

    /**
     * @inheritDoc
     */
    public function getFileType(bool $withEncoding = false): ?string
    {
        if ($this->path->real === null) {
            return null;
        }

        // detect mimetype by magic.mime
        $type = (new \finfo($withEncoding ? FILEINFO_MIME : FILEINFO_MIME_TYPE))->file($this->path->raw);
        if (!in_array($type, [false, 'text/plain', 'application/octet-stream', 'inode/x-empty'], true)) {
            return $type;
        }

        // detect mimetype by file-extension
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
    public function getTime(): int
    {
        return $this->path->fileInfo()->getMTime();
    }

    /**
     * @inheritDoc
     */
    public function touch(bool $ifNewOnly = false): bool
    {
        if ($ifNewOnly === true && $this->isFile()) {
            return true;
        }

        // actual touch file
        if (!touch($this->path->raw)) {
            return false;
        }

        // reset internal path-state to re-evaluate the realpath
        $this->path->reload();

        return true;
    }

    /**
     * @return bool
     */
    public function mkdir():bool
    {
        if (mkdir($this->path->raw, 0777, true)) {
            $this->path->reload();
            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function isDotfile(): bool
    {
        return strpos($this->storage->path()->basename, '.') === 0;
    }
}
