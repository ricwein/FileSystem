<?php

/**
 * @author Richard Weinhold
 */

namespace ricwein\FileSystem\Storage;

use DirectoryIterator;
use finfo;
use Generator;
use Iterator;
use League\Flysystem\FileExistsException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ricwein\FileSystem\Exceptions\Exception;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\FileSystem;
use ricwein\FileSystem\Enum\Hash;
use ricwein\FileSystem\Helper\Stream;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Helper\Path;
use ricwein\FileSystem\Helper\MimeType;
use ricwein\FileSystem\Storage\Extensions\Binary;
use SplFileInfo;
use League\Flysystem\FileNotFoundException as FlySystemFileNotFoundException;

/**
 * represents a file/directory at the local filesystem
 */
class Disk extends Storage
{
    /**
     * @var Path
     */
    protected Path $path;

    /**
     * @param string[]|FileSystem[]|Path[] $path
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function __construct(...$path)
    {
        if (empty($path)) {
            throw new RuntimeException('unable to load Disk-Storage without a path', 400);
        }
        $this->path = new Path($path);
    }

    /**
     * @return void
     * @throws AccessDeniedException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function __destruct()
    {
        if (!$this->selfdestruct || !file_exists($this->path->raw)) {
            return;
        }

        if (is_file($this->path->raw)) {
            $this->removeFile();
        } elseif (is_dir($this->path->raw)) {
            $this->removeDir();
        }
    }

    /**
     * @inheritDoc
     * @return array
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function getDetails(): array
    {
        return array_merge(parent::getDetails(), $this->path->getDetails());
    }

    /**
     * @inheritDoc
     * @return bool
     * @throws RuntimeException
     * @throws UnexpectedValueException
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
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function isFile(): bool
    {
        return $this->path->real !== null && file_exists($this->path->real) && $this->path->fileInfo()->isFile();
    }

    /**
     * @inheritDoc
     * @return bool
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function isDir(): bool
    {
        return $this->path->real !== null && file_exists($this->path->real) && $this->path->fileInfo()->isDir();
    }

    /**
     * @inheritDoc
     * @return bool
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function isExecutable(): bool
    {
        return $this->isFile() && $this->path->fileInfo()->isExecutable();
    }

    /**
     * @inheritDoc
     * @return bool
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function isSymlink(): bool
    {
        return $this->path->real !== null && $this->path->fileInfo()->isLink();
    }

    /**
     * @inheritDoc
     * @return bool
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function isReadable(): bool
    {
        return $this->path->real !== null && $this->path->fileInfo()->isReadable();
    }

    /**
     * @inheritDoc
     * @return bool
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function isWriteable(): bool
    {
        return $this->path->fileInfo()->isWritable();
    }

    /**
     * @inheritDoc
     * @param int|null $offset
     * @param int|null $length
     * @param int $mode
     * @return string
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function readFile(?int $offset = null, ?int $length = null, int $mode = LOCK_SH): string
    {
        if (!$this->isFile() || !$this->isReadable()) {
            throw new FileNotFoundException('file not found', 404);
        }

        // open file-handler in readonly mode
        $handle = $this->getStream('r');

        try {

            // try to set lock if provided
            if ($mode !== 0 && !flock($handle, $mode | LOCK_NB)) {
                throw new RuntimeException('unable to get file-lock', 500);
            }

            return (new Stream($handle))->read($offset, $length);
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
     * @return array
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function readFileAsLines(): array
    {
        if (!$this->isFile() || !$this->isReadable()) {
            throw new FileNotFoundException('file not found', 404);
        }

        return file($this->path->real);
    }

    /**
     * @inheritDoc
     * @param int|null $offset
     * @param int|null $length
     * @param int $mode
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function streamFile(?int $offset = null, ?int $length = null, int $mode = LOCK_SH): void
    {
        if (!$this->isFile() || !$this->isReadable()) {
            throw new FileNotFoundException('file not found', 404);
        }

        // open file-handler in readonly mode
        $handle = $this->getStream('r');

        try {

            // try to set lock if provided
            if ($mode !== 0 && !flock($handle, $mode | LOCK_NB)) {
                throw new RuntimeException('unable to get file-lock', 500);
            }

            (new Stream($handle))->send($offset, $length);
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
     * @throws Exception
     */
    public function writeFile(string $content, bool $append = false, int $mode = LOCK_EX): bool
    {
        // ensure file exists and path-real is set correctly
        $this->touch(true);

        // open file-handler in readonly mode
        $handle = $this->getStream($append ? 'a' : 'w');

        try {

            // try to set lock if provided
            if ($mode !== 0 && !flock($handle, $mode | LOCK_NB)) {
                throw new RuntimeException('unable to get file-lock', 500);
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
        $this->selfdestruct = false;

        if (!unlink($this->path->real ?? $this->path->raw)) {
            return false;
        }

        $this->path->reload();
        return true;
    }


    /**
     * @param bool $recursive
     * @param int $mode
     * @return Iterator
     */
    protected function getIterator(bool $recursive, int $mode = RecursiveIteratorIterator::SELF_FIRST): Iterator
    {
        if (!$recursive) {
            return new DirectoryIterator($this->path->real);
        }

        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->path->real, RecursiveDirectoryIterator::SKIP_DOTS),
            $mode
        );
    }


    /**
     * removes directory from disk
     * @return bool
     * @throws AccessDeniedException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function removeDir(): bool
    {
        if (!$this->isDir()) {
            throw new AccessDeniedException(sprintf('unable to remove directory for path: "%s"', $this->path->raw), 500);
        }

        try {
            $iterator = $this->getIterator(true, RecursiveIteratorIterator::CHILD_FIRST);

            /** @var SplFileInfo $file */
            foreach ($iterator as $splFile) {

                // file not readable
                if (!$splFile->isReadable()) {
                    throw new AccessDeniedException(sprintf('unable to access file for path: "%s"', $splFile->getPathname()), 500);
                }

                // try to remove files/dirs/links
                switch ($splFile->getType()) {
                    case 'dir':
                        rmdir($splFile->getRealPath());
                        break;
                    case 'link':
                        unlink($splFile->getPathname());
                        break;
                    default:
                        unlink($splFile->getRealPath());
                }
            }

            return rmdir($this->path->raw);
        } finally {
            $this->selfdestruct = false;
            $this->path->reload();
        }
    }

    /**
     * @inheritDoc
     * @param bool $recursive
     * @return Generator
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function list(bool $recursive = false): Generator
    {
        if (!$this->isDir()) {
            throw new RuntimeException(sprintf('unable to open directory "%s"', $this->path->raw), 500);
        }

        $iterator = $this->getIterator($recursive);

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (in_array($file->getFilename(), ['.', '..'], true)) {
                continue;
            }

            yield new self(
                $this->path->real,
                str_replace($this->path->real, '', $file->getPathname())
            );
        }
    }

    /**
     * @inheritDoc
     * @return int
     * @throws RuntimeException
     * @throws UnexpectedValueException
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
        $type = (new finfo($withEncoding ? FILEINFO_MIME : FILEINFO_MIME_TYPE))->file($this->path->raw);
        if (!in_array($type, [false, 'text/plain', 'application/octet-stream', 'inode/x-empty'], true)) {
            return $type;
        }

        // detect mimetype by file-extension
        return MimeType::getMimeFor($this->path->extension);
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
            case Hash::CONTENT:
                return hash_file($algo, $this->path->real, false);
            case Hash::FILENAME:
                return hash($algo, $this->path->filename, false);
            case Hash::FILEPATH:
                return hash($algo, $this->path->real, false);
            default:
                throw new RuntimeException('unknown hashing-mode', 500);
        }
    }

    /**
     * @inheritDoc
     * @return int
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function getTime(): int
    {
        return $this->path->fileInfo()->getMTime();
    }

    /**
     * @inheritDoc
     */
    public function touch(bool $ifNewOnly = false, ?int $time = null, ?int $atime = null): bool
    {
        if ($ifNewOnly === true && $this->isFile()) {
            return true;
        }

        // actual touch file
        if (!touch($this->path->raw, $time, $atime)) {
            return false;
        }

        // reset internal path-state to re-evaluate the realpath
        $this->path->reload();

        return true;
    }

    /**
     * @param bool $ifNewOnly
     * @return bool
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function mkdir(bool $ifNewOnly = false): bool
    {
        if ($ifNewOnly && $this->isDir() && $this->isFile()) {
            return true;
        }

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
        return strpos($this->path->basename, '.') === 0;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return sprintf('%s at: "%s"', parent::__toString(), (string)$this->path);
    }

    /**
     * @inheritDoc
     * @param int $mode
     * @return Binary\Disk
     * @throws AccessDeniedException
     * @throws RuntimeException
     */
    public function getHandle(int $mode): Binary
    {
        return new Binary\Disk($mode, $this);
    }

    /**
     * changes current directory
     * @param string[]|FileSystem[]|Path[] $path
     * @return void
     * @throws UnexpectedValueException
     */
    public function cd(array $path): void
    {
        array_unshift($path, $this->path);
        $this->path = new Path($path);
    }


    /**
     * @inheritDoc
     * @throws RuntimeException
     */
    public function getStream(string $mode = 'r+')
    {
        $stream = fopen($this->path->real, $mode);

        if ($stream === false) {
            throw new RuntimeException('failed to open stream', 500);
        }

        return $stream;
    }

    /**
     * @inheritDoc
     * @throws RuntimeException
     */
    public function writeFromStream($stream): bool
    {
        if (!is_resource($stream)) {
            throw new RuntimeException(sprintf('file-handle must be of type \'resource\' but \'%s\' given', is_object($stream) ? get_class($stream) : gettype($stream)), 500);
        }

        $destStream = $this->getStream('w');
        try {
            if (!stream_copy_to_stream($stream, $destStream)) {
                return false;
            }

            return true;
        } finally {
            fclose($destStream);
        }
    }

    /**
     * @inheritDoc
     * @param Storage $destination
     * @return bool
     * @throws Exception
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws FileExistsException
     * @throws FlySystemFileNotFoundException
     */
    public function copyFileTo(Storage $destination): bool
    {
        switch (true) {

            case $destination instanceof Disk:

                // copy file from disk to disk
                if (!copy($this->path->real, $destination->path()->raw)) {
                    return false;
                }
                $destination->path()->reload();
                return true;

            case $destination instanceof Flysystem:
                $readStream = $this->getStream('r');
                try {
                    if ($destination->writeFromStream($readStream) === true) {
                        return true;
                    }
                    return false;
                } finally {
                    fclose($readStream);
                }

            case $destination instanceof Memory:
            default:
                return $destination->writeFile($this->readFile());
        }
    }

    /**
     * @inheritDoc
     * @param Storage $destination
     * @return bool
     * @throws Exception
     * @throws FileExistsException
     * @throws FileNotFoundException
     * @throws FlySystemFileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function moveFileTo(Storage $destination): bool
    {
        switch (true) {

            // copy file from disk to disk
            case $destination instanceof Disk:
                if (!rename($this->path->real, $destination->path()->raw)) {
                    return false;
                }
                $destination->path()->reload();
                return true;

            case $destination instanceof Flysystem:
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
