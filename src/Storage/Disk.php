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
use ricwein\FileSystem\Enum\Hash;
use ricwein\FileSystem\Enum\Time;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\FileSystem;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Helper\MimeType;
use ricwein\FileSystem\Helper\Stream;
use ricwein\FileSystem\Path;
use ricwein\FileSystem\Storage\Extensions\Binary;
use SplFileInfo;
use function hash;
use function hash_file;

/**
 * represents a file/directory at the local filesystem
 */
class Disk extends BaseStorage implements FileStorageInterface, DirectoryStorageInterface
{
    /**
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function __construct(string|FileSystem|Path|self|SplFileInfo ...$path)
    {
        if (empty($path)) {
            throw new RuntimeException('unable to load Disk-Storage without a path', 400);
        }
        $this->path = new Path(...$path);
    }

    /**
     * @throws AccessDeniedException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws FileNotFoundException
     * @throws UnsupportedException
     */
    public function __destruct()
    {
        if (!$this->selfDestruct) {
            return;
        }

        if ($this->path->isFile()) {
            $this->removeFile();
            return;
        }

        if ($this->path->isDir()) {
            $this->removeDir();
        }
    }

    /**
     * @inheritDoc
     */
    public function getDetails(): array
    {
        return array_merge(parent::getDetails(), $this->path->__debugInfo());
    }

    /**
     * @inheritDoc
     */
    public function doesSatisfyConstraints(): bool
    {
        return $this->constraints->isValidPath($this->path);
    }

    /**
     * @inheritDoc
     */
    public function isFile(): bool
    {
        return $this->path->isFile();
    }

    /**
     * @inheritDoc
     */
    public function isDir(): bool
    {
        return $this->path->isDir();
    }

    /**
     * @inheritDoc
     */
    public function isExecutable(): bool
    {
        return $this->isFile() && $this->path->isExecutable();
    }

    /**
     * @inheritDoc
     */
    public function isSymlink(): bool
    {
        return $this->path->doesExist() && $this->path->isLink();
    }

    /**
     * @inheritDoc
     */
    public function isReadable(): bool
    {
        return $this->path->isReadable();
    }

    /**
     * @inheritDoc
     */
    public function isWriteable(): bool
    {
        return $this->path->isWritable();
    }

    /**
     * @inheritDoc
     * @throws FileNotFoundException
     * @throws RuntimeException
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
     * @throws FileNotFoundException
     */
    public function readFileAsLines(): array
    {
        if (!$this->isFile() || !$this->isReadable() || (null === $realpath = $this->path->getRealPath())) {
            throw new FileNotFoundException('File not found or not readable', 404);
        }

        return file($realpath);
    }

    /**
     * @inheritDoc
     * @throws FileNotFoundException
     * @throws RuntimeException
     */
    public function streamFile(int $offset = 0, ?int $length = null, int $mode = LOCK_SH): void
    {
        if (!$this->isFile() || !$this->isReadable()) {
            throw new FileNotFoundException('File not found or not readable', 404);
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

        if (null === $realpath = $this->path->getRealPath()) {
            return true;
        }

        if (!unlink($realpath)) {
            return false;
        }

        $this->path->reload();
        return true;
    }

    /**
     * @throws FileNotFoundException
     */
    protected function getIterator(bool $recursive, ?int $constraints = null, ?callable $filter = null, int $mode = RecursiveIteratorIterator::SELF_FIRST): Iterator
    {
        if (null === $realpath = $this->path->getRealPath()) {
            throw new FileNotFoundException('File or Directory not found', 404);
        }

        if (!$recursive) {
            $innerIterator = new DirectoryIterator($realpath);

            if ($filter === null) {
                return $innerIterator;
            }

            return new CallbackFilterIterator($innerIterator, $filter);
        }

        if (($constraints ?? $this->constraints->getConstraints()) & Constraint::DISALLOW_LINK) {
            $innerIterator = new RecursiveDirectoryIterator($realpath, FilesystemIterator::SKIP_DOTS);
        } else {
            $innerIterator = new RecursiveDirectoryIterator($realpath, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS);
        }

        if ($filter === null) {
            return new RecursiveIteratorIterator($innerIterator, $mode);
        }

        return new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator($innerIterator, $filter), $mode);
    }


    /**
     * removes directory from disk
     * @throws AccessDeniedException
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     * @internal
     */
    public function removeDir(): bool
    {
        if (!$this->isDir()) {
            throw new FileNotFoundException(sprintf('Unable to remove directory for path: "%s"', $this->path->getRawPath()), 500);
        }

        try {
            $iterator = $this->getIterator(true, null, null, RecursiveIteratorIterator::CHILD_FIRST);

            /** @var SplFileInfo $file */
            foreach ($iterator as $splFile) {

                // file not readable
                if (!$splFile->isReadable()) {
                    throw new AccessDeniedException(sprintf('Unable to access file for path: "%s"', $splFile->getPathname()), 500);
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

            return rmdir($this->path->getRawPath());
        } finally {
            $this->selfDestruct = false;
            $this->path->reload();
        }
    }

    /**
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @inheritDoc
     * @internal
     */
    public function list(bool $recursive = false, ?int $constraints = null, ?callable $iteratorFilter = null): Generator
    {
        if (!$this->isDir()) {
            throw new RuntimeException(sprintf('unable to open directory "%s"', $this->path->getRawPath()), 500);
        }

        $iterator = $this->getIterator($recursive, $constraints, $iteratorFilter);

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (in_array($file->getFilename(), ['.', '..'], true)) {
                continue;
            }

            yield new self($this->path->getRealPath(), str_replace($this->path->getRealPath(), '', $file->getPathname()));
        }
    }

    /**
     * @inheritDoc
     */
    public function getSize(): int
    {
        return $this->path->getSize();
    }

    /**
     * @inheritDoc
     */
    public function getFileType(bool $withEncoding = false): ?string
    {
        if (!$this->path->doesExist()) {
            return null;
        }

        // detect mimetype by magic.mime
        $fileInfo = new finfo($withEncoding ? FILEINFO_MIME : FILEINFO_MIME_TYPE);
        $type = $fileInfo->file($this->path->getRawPath());

        if (!in_array($type, [false, 'text/plain', 'application/octet-stream', 'inode/x-empty'], true)) {
            return $type;
        }

        // detect mimetype by file-extension
        return MimeType::getMimeFor($this->path->getExtension());
    }

    /**
     * @inheritDoc
     */
    public function getFileHash(Hash $mode = Hash::CONTENT, string $algo = 'sha256', bool $raw = false): ?string
    {
        if (in_array($mode, [Hash::CONTENT, Hash::FILEPATH], true)) {
            if (false === $realPath = $this->path->getRealPath()) {
                return null;
            }

            return match ($mode) {
                Hash::CONTENT => hash_file($algo, $realPath, $raw),
                Hash::FILEPATH => hash($algo, $realPath, $raw),
            };
        }

        /** @noinspection PhpUncoveredEnumCasesInspection */
        return match ($mode) {
            Hash::FILENAME => hash($algo, $this->path->getFilename(), $raw),
            Hash::LAST_MODIFIED => hash($algo, (string)$this->getTime(), $raw),
        };
    }

    /**
     * @inheritDoc
     */
    public function getTime(Time $type = Time::LAST_MODIFIED): ?int
    {
        return match ($type) {
            Time::LAST_MODIFIED => $this->path->getMTime(),
            Time::LAST_ACCESSED => $this->path->getATime(),
            Time::CREATED => $this->path->getCTime(),
        };

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
            $result = touch($this->path->getRawPath(), $time, $atime);

        } elseif ($time !== null) {

            $result = touch($this->path->getRawPath(), $time);

        } else {

            $result = touch($this->path->getRawPath());

        }

        if (!$result) {
            return false;
        }

        // reset internal path-state to re-evaluate the realpath
        $this->path->reload();

        return true;
    }

    public function mkdir(bool $ifNewOnly = false, int $permissions = 0755): bool
    {
        if ($ifNewOnly && $this->isDir() && $this->isFile()) {
            return true;
        }

        if (mkdir($concurrentDirectory = $this->path->getRawPath(), $permissions, true) || !is_dir($concurrentDirectory)) {
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
        return $this->path->isDotfile();
    }

    public function __toString(): string
    {
        return sprintf('%s at: "%s"', parent::__toString(), $this->path);
    }

    /**
     * @inheritDoc
     * @throws AccessDeniedException
     * @throws RuntimeException
     */
    public function getHandle(int $mode): Binary\Disk
    {
        return new Binary\Disk($mode, $this);
    }

    /**
     * changes current directory
     * @param string[]|FileSystem[]|Path[]|self[] $path
     * @throws UnexpectedValueException
     * @throws RuntimeException
     * @internal
     */
    public function cd(array $path): void
    {
        array_unshift($path, $this->path);
        $this->path = new Path(...$path);
    }


    /**
     * @inheritDoc
     * @throws RuntimeException
     */
    public function getStream(string $mode = 'rb+'): Stream
    {
        return Stream::fromResourceName($this->path->getRawPath(), $mode);
    }

    /**
     * @inheritDoc
     * @throws RuntimeException
     */
    public function writeFromStream(Stream $stream): bool
    {
        $destHandle = Stream::fromResourceName($this->path->getRawPath(), 'wb');

        try {
            $stream->copyToStream($destHandle);
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * @inheritDoc
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws FilesystemException
     */
    public function copyFileTo(BaseStorage $destination): bool
    {
        switch (true) {

            case $destination instanceof self:

                // copy file from disk to disk
                if (!copy(from: $this->path->getRealOrRawPath(), to: $destination->getPath()->getRawPath())) {
                    return false;
                }

                $destination->getPath()->reload();
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
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws FilesystemException
     */
    public function moveFileTo(BaseStorage $destination): bool
    {
        switch (true) {

            // copy file from disk to disk
            case $destination instanceof self:
                if (!rename(from: $this->path->getRealOrRawPath(), to: $destination->getPath()->getRawPath())) {
                    return false;
                }
                $destination->getPath()->reload();
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
     * @internal
     */
    public function copyDirectoryTo(self $destination): bool
    {
        $result = $this->copyDirectory(sourcePath: $this->path->getRealOrRawPath(), destinationPath: $destination->getPath()->getRawPath());

        $destination->getPath()->reload();

        return $result;
    }

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

                $sourceFilePath = "$sourcePath/$file";
                $destinationFilePath = "$destinationPath/$file";

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
