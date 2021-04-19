<?php

/**
 * @author Richard Weinhold
 */

declare(strict_types=1);

namespace ricwein\FileSystem\Storage;

use CallbackFilterIterator;
use DirectoryIterator;
use FilesystemIterator;
use finfo;
use Generator;
use Iterator;
use League\Flysystem\FilesystemException;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ricwein\FileSystem\Enum\Time;
use ricwein\FileSystem\Exceptions\Exception;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Helper\Constraint;
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
     * @throws FileNotFoundException
     */
    public function __destruct()
    {
        if (!$this->selfDestruct || !file_exists($this->path->raw)) {
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
     * @param int $offset
     * @param int|null $length
     * @param int $mode
     * @return string
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function readFile(int $offset = 0, ?int $length = null, int $mode = LOCK_SH): string
    {
        if (!$this->isFile() || !$this->isReadable()) {
            throw new FileNotFoundException('file not found', 404);
        }

        // open file-handler in readonly mode
        $stream = $this->getStream('rb');
        try {

            $stream->lock($mode | LOCK_NB);
            return $stream->read($offset, $length);
        } finally {
            $stream->unlock();
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
     * @param int $offset
     * @param int|null $length
     * @param int $mode
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function streamFile(int $offset = 0, ?int $length = null, int $mode = LOCK_SH): void
    {
        if (!$this->isFile() || !$this->isReadable()) {
            throw new FileNotFoundException('file not found', 404);
        }

        // open file-handler in readonly mode
        $stream = $this->getStream('rb');
        try {

            // try to set lock if provided
            $stream->lock($mode);
            $stream->passthru($offset, $length);
        } finally {
            $stream->unlock();
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
        $stream = $this->getStream($append ? 'ab' : 'wb');

        try {

            // try to set lock if provided
            $stream->lock($mode);
            $stream->write($content);

            $this->path->reload();
            return true;
        } finally {
            $stream->unlock();
        }
    }

    /**
     * @inheritDoc
     */
    public function removeFile(): bool
    {
        $this->selfDestruct = false;

        if (!unlink($this->path->real ?? $this->path->raw)) {
            return false;
        }

        $this->path->reload();
        return true;
    }


    /**
     * @param bool $recursive
     * @param int|null $constraints
     * @param callable|null $filter
     * @param int $mode
     * @return Iterator
     */
    protected function getIterator(bool $recursive, ?int $constraints = null, ?callable $filter = null, int $mode = RecursiveIteratorIterator::SELF_FIRST): Iterator
    {
        if (!$recursive) {
            $innerIterator = new DirectoryIterator($this->path->real);

            if ($filter === null) {
                return $innerIterator;
            }

            return new CallbackFilterIterator($innerIterator, $filter);
        }

        if (($constraints ?? $this->constraints->getConstraints()) & Constraint::DISALLOW_LINK) {
            $innerIterator = new RecursiveDirectoryIterator($this->path->real, FilesystemIterator::SKIP_DOTS);
        } else {
            $innerIterator = new RecursiveDirectoryIterator($this->path->real, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS);
        }

        if ($filter === null) {
            return new RecursiveIteratorIterator($innerIterator, $mode);
        }

        return new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator($innerIterator, $filter), $mode);
    }


    /**
     * removes directory from disk
     * @return bool
     * @throws AccessDeniedException
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @internal
     */
    public function removeDir(): bool
    {
        if (!$this->isDir()) {
            throw new FileNotFoundException(sprintf('unable to remove directory for path: "%s"', $this->path->raw), 500);
        }

        try {
            $iterator = $this->getIterator(true, null, null, RecursiveIteratorIterator::CHILD_FIRST);

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
            $this->selfDestruct = false;
            $this->path->reload();
        }
    }

    /**
     * @param bool $recursive
     * @param int|null $constraints
     * @param callable|null $iteratorFilter
     * @return Generator
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @internal
     */
    public function list(bool $recursive = false, ?int $constraints = null, ?callable $iteratorFilter = null): Generator
    {
        if (!$this->isDir()) {
            throw new RuntimeException(sprintf('unable to open directory "%s"', $this->path->raw), 500);
        }

        $iterator = $this->getIterator($recursive, $constraints, $iteratorFilter);

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
     * @throws UnexpectedValueException
     */
    public function getFileHash(int $mode = Hash::CONTENT, string $algo = 'sha256', bool $raw = false): ?string
    {
        if ($this->path->real === null) {
            return null;
        }

        return match ($mode) {
            Hash::CONTENT => hash_file($algo, $this->path->real, $raw),
            Hash::FILENAME => hash($algo, $this->path->filename, $raw),
            Hash::FILEPATH => hash($algo, $this->path->real, $raw),
            Hash::LAST_MODIFIED => hash($algo, (string)$this->getTime(), $raw),
            default => throw new RuntimeException('unknown hashing-mode', 500),
        };
    }

    /**
     * @inheritDoc
     * @return int
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function getTime(int $type = Time::LAST_MODIFIED): ?int
    {
        switch ($type) {
            case Time::LAST_MODIFIED:
                return $this->path->fileInfo()->getMTime();
            case Time::LAST_ACCESSED:
                return $this->path->fileInfo()->getATime();
            case Time::CREATED:
                return $this->path->fileInfo()->getCTime();
        }

        return null;
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
        if ($atime !== null && $time !== null) {

            /** @noinspection PotentialMalwareInspection dafuq? */
            $result = touch($this->path->raw, $time, $atime);

        } elseif ($time !== null) {

            $result = touch($this->path->raw, $time);

        } else {

            $result = touch($this->path->raw);

        }

        if (!$result) {
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

        if (mkdir($concurrentDirectory = $this->path->raw, 0777, true) || !is_dir($concurrentDirectory)) {
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
        return str_starts_with($this->path->basename, '.');
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
     * @internal
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
    public function getStream(string $mode = 'rb+'): Stream
    {
        return Stream::fromResourceName($this->path->real, $mode);
    }

    /**
     * @inheritDoc
     * @throws RuntimeException
     */
    public function writeFromStream(Stream $stream): bool
    {
        $destHandle = Stream::fromResourceName($this->path->real, 'wb');

        try {
            $stream->copyToStream($destHandle);
            return true;
        } catch (RuntimeException) {
            return false;
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
     * @throws FilesystemException
     */
    public function copyFileTo(Storage $destination): bool
    {
        switch (true) {

            case $destination instanceof self:

                // copy file from disk to disk
                if (!copy($this->path->real, $destination->path()->raw)) {
                    return false;
                }
                $destination->path()->reload();
                return true;

            case $destination instanceof Flysystem:
                $readStream = $this->getStream('rb');
                return $destination->writeFromStream($readStream) === true;

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
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws FilesystemException
     */
    public function moveFileTo(Storage $destination): bool
    {
        switch (true) {

            // copy file from disk to disk
            case $destination instanceof self:
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

    /**
     * @param Disk $destination
     * @return bool
     * @internal
     */
    public function copyDirectoryTo(self $destination): bool
    {
        $result = $this->copyDirectory($this->path()->real, $destination->path()->real);
        $this->path->reload();
        return $result;
    }

    /**
     * @param string $sourcePath
     * @param string $destinationPath
     * @return bool
     */
    protected function copyDirectory(string $sourcePath, string $destinationPath): bool
    {
        // open the source directory
        $sourceDirectoryHandle = opendir($sourcePath);

        try {
            if (!is_dir($destinationPath) && !mkdir($destinationPath) && !is_dir($destinationPath)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $destinationPath));
            }

            // Loop through the files in source directory
            while ($file = readdir($sourceDirectoryHandle)) {

                // skip dotfiles
                if (in_array($file, ['.', '..'], true)) {
                    continue;
                }

                $sourceFilePath = "{$sourcePath}/{$file}";
                $destinationFilePath = "{$destinationPath}/{$file}";

                if (is_dir($sourceFilePath)) {

                    // recursively copy directory content
                    if (!$this->copyDirectory($sourceFilePath, $destinationFilePath)) {
                        return false;
                    }
                    continue;

                }

                if (!copy($sourceFilePath, $destinationFilePath)) {
                    return false;
                }
            }

            return true;

        } finally {
            closedir($sourceDirectoryHandle);
        }
    }
}
