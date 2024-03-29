<?php

/**
 * @author Richard Weinhold
 */

declare(strict_types=1);

namespace ricwein\FileSystem\File;

use Exception;
use League\Flysystem\FilesystemException as FlySystemException;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\Hint;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\FileSystem;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Helper\DirectoryIterator;
use ricwein\FileSystem\Helper\MimeType;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Storage\BaseStorage;
use ZipArchive;

/**
 * represents a zip-file,
 * utilizing php's ZipArchive-class
 */
class Zip extends File
{
    /**
     * @var string[]
     */
    private const ERROR_MESSAGES = [ZipArchive::ER_EXISTS => 'file already exists', ZipArchive::ER_INCONS => 'archive is inconsistent', ZipArchive::ER_INVAL => 'invalid argument', ZipArchive::ER_MEMORY => 'memory-malloc failure', ZipArchive::ER_NOENT => 'file not found', ZipArchive::ER_NOZIP => 'not a zip archive', ZipArchive::ER_TMPOPEN => 'unable to create temporary file', ZipArchive::ER_OPEN => 'unable to open file', ZipArchive::ER_CLOSE => 'closing zip archive failed', ZipArchive::ER_ZIPCLOSED => 'zip archive was closed', ZipArchive::ER_READ => 'unable to read file', ZipArchive::ER_WRITE => 'unable to write file', ZipArchive::ER_SEEK => 'seek failed', ZipArchive::ER_MULTIDISK => 'multi-disk zip archives not supported', ZipArchive::ER_RENAME => 'renaming temporary file failed', ZipArchive::ER_CRC => 'invalid CRC', ZipArchive::ER_ZLIB => 'error in zlib', ZipArchive::ER_CHANGED => 'entry has been changed', ZipArchive::ER_DELETED => 'entry has been deleted', ZipArchive::ER_COMPNOTSUPP => 'compression method not supported', ZipArchive::ER_EOF => 'premature EOF', ZipArchive::ER_INTERNAL => 'internal error', ZipArchive::ER_REMOVE => 'can\'t remove file',];

    /**
     * automatically close and reopened zip-archive after x files added,
     * to prevent running into file descriptors limit
     */
    private const AUTO_COMMIT_AFTER = 128;
    public const DEFAULT_ENCRYPTION = ZipArchive::EM_AES_256;

    /**
     * indicates whether the zip-archive is currently opened
     */
    protected bool $isOpen = false;

    protected ZipArchive $archive;

    protected int $flags;

    private ?string $password = null;

    protected int $encryption = self::DEFAULT_ENCRYPTION;
    protected int $compression = ZipArchive::CM_DEFAULT;

    protected int $fileCounter = 0;

    /** @var Storage\Disk */
    protected BaseStorage $storage;

    /**
     * @inheritDoc
     */
    public function __construct(Storage\Disk $storage, int $constraints = Constraint::STRICT, int $flags = ZipArchive::CREATE)
    {
        $this->flags = $flags;
        $this->archive = new ZipArchive();

        if (($flags & ZipArchive::CREATE) === ZipArchive::CREATE && $storage instanceof Storage\Disk\Temp) {
            FileSystem::__construct($storage, $constraints);
        } else {
            parent::__construct($storage, $constraints);
        }

    }

    /**
     * @inheritDoc
     */
    public function __destruct()
    {
        if ($this->isOpen) {
            $this->closeArchive();
        }
        parent::__destruct();
    }

    /**
     * closes internale zip-archive
     */
    protected function closeArchive(): bool
    {
        if (!$this->isOpen) {
            return false;
        }

        if (!$this->archive->close()) {
            return false;
        }

        $this->password = null;
        $this->isOpen = false;
        return true;
    }

    /**
     * open internal zip-archive with given flags
     * => this can create a new zip-file
     * @throws ConstraintsException
     * @throws RuntimeException
     */
    protected function openArchive(): bool
    {
        if ($this->isOpen) {
            return false;
        }

        $result = $this->archive->open($this->getPath()->getRawPath(), $this->flags);

        if (($this->flags & ZipArchive::CREATE) === ZipArchive::CREATE && $result === ZipArchive::ER_NOZIP && $this->storage->isFile()) {
            throw new RuntimeException(
                sprintf('[%d] Error while opening ZipArchive: "%s"', $result, static::ERROR_MESSAGES[$result]),
                500,
                new Hint("The zip-file probably already exists, but should be created since 'ZipArchive::CREATE' is set.")
            );
        }


        // something went wrong, search and throw error-message
        if ($result !== true) {
            if (array_key_exists($result, static::ERROR_MESSAGES)) {
                throw new RuntimeException(sprintf('[%d] Error while opening ZipArchive: "%s"', $result, static::ERROR_MESSAGES[$result]), 500);
            }

            throw new RuntimeException(sprintf('[%d] Error while opening ZipArchive: "unknown"', $result), 500);
        }

        // validate zip-file constraints
        if (!$this->storage->doesSatisfyConstraints()) {
            $this->archive->close();
            throw $this->storage->getConstraintViolations();
        }

        if ($this->password !== null) {
            $this->archive->setPassword($this->password);
        }

        $this->getPath()->reload();

        $this->isOpen = true;
        return true;
    }

    /**
     * finish last running zip-transaction and close archive
     */
    public function commit(): self
    {
        $this->closeArchive();
        return $this;
    }

    /**
     * en/decrypt archive with given password
     * @param int|null $encryption encryption-mode
     */
    public function withPassword(?string $password = null, ?int $encryption = null): self
    {
        $this->password = $password;

        if ($password !== null && $this->isOpen) {
            $this->archive->setPassword($password);

            if ($encryption !== null) {
                $this->encryption = $encryption;
            }
        }

        return $this;
    }

    /**
     * @param int $mode ZipArchive::CM_DEFAULT | CM_STORE | CM_SHRINK | CM_DEFLATE
     */
    public function setCompression(int $mode): self
    {
        $this->compression = $mode;
        return $this;
    }

    /**
     * @param string[]|null $entries
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws RuntimeException
     * @throws UnsupportedException
     */
    public function extractTo(Storage\Disk $destination, ?int $constraints = null, ?array $entries = null): Directory
    {
        if (!$this->isOpen) {
            $this->openArchive();
        }

        // validate directory-constraints
        if (!$destination->doesSatisfyConstraints()) {
            throw $destination->getConstraintViolations();
        }

        $destinationDir = new Directory($destination, $constraints ?? $this->storage->getConstraints());

        // create destination dir
        if (!$destinationDir->isDir() && $destinationDir->storage()->isFile()) {
            $destinationDir->mkdir();
        }

        // try to extract archive (or selected entries only)
        $result = $this->archive->extractTo($destinationDir->getPath()->getRawPath(), $entries);

        if ($result !== true) {
            throw new RuntimeException(sprintf('unable to extract ZipArchive in path: "%s"%s', $destinationDir->getPath()->getRawPath(), $this->password === null ? '' : ', wrong password?'), 500);
        }

        return $destinationDir;
    }

    /**
     * add file or directory to zip-archive
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws FlySystemException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function add(FileSystem $file, ?string $asNode = null): self
    {
        if ($file instanceof Directory) {
            return $this->addDirectory($file, $asNode ?? '/');
        }

        if ($file instanceof File) {
            return $this->addFile($file, $asNode);
        }

        throw new UnexpectedValueException(sprintf('%s::%s($file) only supports Directory and File as $file type, but is %s', static::class, __METHOD__, $file::class));
    }

    /**
     * add file or directory storage to zip-archive
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws FlySystemException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function addStorage(BaseStorage $storage, ?string $asNode = null): self
    {
        if ($storage->isDir()) {
            return $this->addDirectoryStorage($storage, $asNode ?? '/');
        }

        return $this->addFileStorage($storage, $asNode);
    }

    /**
     * adds directory to zip-archive
     * @param string $toNode adds content of directory to a subdirectory, or the root of the zip-archive
     * @param callable|null $filter DirectoryIterator-Filter in format: function(Storage $file): bool
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws FlySystemException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function addDirectory(Directory $directory, string $toNode = '/', ?callable $filter = null): self
    {
        return $this->addDirectoryStorage($directory->storage(), $toNode, $filter);
    }

    /**
     * @param string $toNode adds content of directory to a subdirectory, or the root of the zip-archive
     * @param callable|null $filter DirectoryIterator-Filter in format: function(Storage $file): bool
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws FlySystemException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function addDirectoryStorage(BaseStorage $storage, string $toNode = '/', ?callable $filter = null): self
    {
        $path = $storage->getPath();
        return $this->addDirectoryStorageContent($storage, rtrim($toNode, '/') . '/' . basename($path->getRawPath()) . '/', $filter);
    }

    /**
     * adds directory-content to zip-archive
     * @param string $toNode adds content of directory to a subdirectory, or the root of the zip-archive
     * @param callable|null $filter DirectoryIterator-Filter in format: function(Storage $file): bool
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws FlySystemException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function addDirectoryContent(Directory $directory, string $toNode = '/', ?callable $filter = null): self
    {
        return $this->addDirectoryStorageContent($directory->storage(), $toNode, $filter);
    }

    /**
     * @param string $toNode adds content of directory to a subdirectory, or the root of the zip-archive
     * @param callable|null $filter DirectoryIterator-Filter in format: function(Storage $file): bool
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     * @throws FlySystemException
     */
    public function addDirectoryStorageContent(BaseStorage $storage, string $toNode = '/', ?callable $filter = null): self
    {
        // prepare recursive directory-iterator
        $iterator = new DirectoryIterator($storage, true);
        if ($filter !== null) {
            $iterator->filterStorage($filter);
        }

        return $this->addDirectoryIterator($iterator, $toNode);
    }

    /**
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws FlySystemException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function addDirectoryIterator(DirectoryIterator $iterator, string $toNode = '/'): self
    {
        $iteratorStorage = $iterator->getStorage();

        /** @var BaseStorage $fileStorage */
        foreach ($iterator->storages() as $fileStorage) {
            if ($fileStorage->isDir()) {
                continue;
            }

            if ($iteratorStorage instanceof Storage\Disk && $fileStorage instanceof Storage\Disk) {
                // relative file-path for in-ziparchive-name:
                if (str_starts_with($fileStorage->getPath()->getRawPath(), $iteratorStorage->getPath()->getRawPath())) {
                    $filepath = str_replace((rtrim($iteratorStorage->getPath()->getRawPath(), '/') . '/'), '', $fileStorage->getPath()->getRawPath());
                } else {
                    $filepath = str_replace((rtrim($iteratorStorage->getPath()->getRealPath(), '/') . '/'), '', $fileStorage->getPath()->getRealPath());
                }

                // append given directory-name:
                $filepath = ltrim(trim($toNode, '/') . '/' . $filepath, '/');

                $this->addFileStorage($fileStorage, $filepath);
                continue;
            }

            if ($fileStorage instanceof Storage\Flysystem) {
                $this->addFileStorage($fileStorage, $fileStorage->getPath()->getRawPath());
                continue;
            }

            $this->addFileStorage($fileStorage);
        }

        return $this;

    }

    /**
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws FlySystemException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function addFile(File $file, ?string $name = null): self
    {
        if (!$file->isFile() || !$file->isReadable()) {
            throw new FileNotFoundException(sprintf('unable to open file: "%s"', $file->storage() instanceof Storage\Disk ? $file->getPath()->getRawPath() : $file->storage()::class), 404);
        }
        return $this->addFileStorage($file->storage(), $name);
    }

    /**
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws FlySystemException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws Exception
     */
    public function addFileStorage(BaseStorage $storage, ?string $name = null): self
    {
        if (!$this->isOpen) {
            $this->openArchive();
        }

        if (!$storage->isFile() || !$storage->isReadable()) {
            throw new FileNotFoundException(sprintf('unable to open file: "%s"', $storage instanceof Storage\Disk ? $storage->getPath()->getRawPath() : $storage::class), 404);
        }

        // add file to archive
        $name = match (true) {
            $storage instanceof Storage\Disk => $this->addFileFromDisk($storage, $name),
            $storage instanceof Storage\Memory => $this->addFileFromMemory($storage, $name),
            $storage instanceof Storage\Flysystem => $this->addFileFromFlysystem($storage, $name),
            default => throw new UnexpectedValueException(sprintf('invalid type for 1 Argument in %s(), expected instance of Storage or File, but "%s" given', __METHOD__, get_debug_type($storage)), 500),
        };

        // set custom compression algorithm
        if ($this->compression !== ZipArchive::CM_DEFAULT && !$this->archive->setCompressionName($name, $this->compression)) {
            throw new RuntimeException(sprintf('failed to set custom compression of type %s for File "%s"', $this->compression, $name), 500);
        }

        // encrypt file in archive if password isset
        if ($this->password !== null && !$this->archive->setEncryptionName($name, $this->encryption, $this->password)) {
            throw new RuntimeException(sprintf('failed to encrypt File "%s"', $name), 500);
        }

        // run auto-commit after x files
        if (++$this->fileCounter > static::AUTO_COMMIT_AFTER) {

            // soft commit:
            // close (and reopen) archive but preserve internal state like: password, etc.
            $this->archive->close();
            $this->isOpen = false;
            $this->fileCounter = 0;
        }

        return $this;
    }

    /**
     * @return string filename in zip-archive
     * @throws RuntimeException
     */
    private function addFileFromDisk(Storage\Disk $file, ?string $name = null): string
    {
        $name = $name ?? $file->getPath()->getFilename();
        $name = str_replace(['\\', DIRECTORY_SEPARATOR], '/', $name);

        if (!$this->archive->addFile($file->getPath()->getRealPath(), $name)) {
            throw new RuntimeException(sprintf('failed to add File "%s" from Disk (%s) to ZipArchive', $name, $file->getPath()->getRealPath()), 500);
        }

        return $name;
    }

    /**
     * @return string filename in zip-archive
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws Exception
     */
    private function addFileFromMemory(Storage\Memory $file, ?string $name = null): string
    {
        // create new file-name
        if ($name === null) {
            $name = sprintf("file.%s", bin2hex(random_bytes(8)));
            $mime = $file->getFileType();

            if (null !== $extension = MimeType::getExtensionFor($mime)) {
                $name .= ".$extension";
            }
        }

        $name = str_replace(['\\', DIRECTORY_SEPARATOR], '/', $name);

        if (!$this->archive->addFromString($name, $file->readFile())) {
            throw new RuntimeException('failed to add File from Memory to ZipArchive', 500);
        }

        return $name;
    }

    /**
     * @return string filename in zip-archive
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws FlySystemException
     */
    private function addFileFromFlysystem(Storage\Flysystem $file, ?string $name = null): string
    {
        $name = $name ?? basename($file->getPath()->getRawPath());
        $name = str_replace(['\\', DIRECTORY_SEPARATOR], '/', $name);

        if (!$this->archive->addFromString($name, $file->readFile())) {
            throw new RuntimeException('failed to add File from Flysystem to ZipArchive', 500);
        }

        return $name;
    }

    /**
     * get archive status
     * @return string
     * @throws ConstraintsException
     * @throws RuntimeException
     */
    public function getStatus(): string
    {
        if (!$this->isOpen) {
            $this->openArchive();
        }

        if (false !== $status = $this->archive->getStatusString()) {
            return (string)$status;
        }

        return 'ERROR!';
    }

    /**
     * @throws ConstraintsException
     * @throws RuntimeException
     */
    public function getFileCount(): int
    {
        if (!$this->isOpen) {
            $this->openArchive();
        }

        return $this->archive->numFiles;
    }

    /**
     * @throws ConstraintsException
     * @throws RuntimeException
     */
    public function setComment(string $comment, ?string $forFile = null): self
    {
        if (!$this->isOpen) {
            $this->openArchive();
        }

        if ($forFile === null) {
            $this->archive->setArchiveComment($comment);
        } else {
            $this->archive->setCommentName($forFile, $comment);
        }

        return $this;
    }

    /**
     * @throws ConstraintsException
     * @throws RuntimeException
     */
    public function getComment(?string $forFile = null): ?string
    {
        if (!$this->isOpen) {
            $this->openArchive();
        }

        $comment = ($forFile !== null) ? $this->archive->getCommentName($forFile) : $this->archive->getArchiveComment();
        return !empty($comment) ? $comment : null;
    }

    /**
     * get stats-array for single entry
     * @throws ConstraintsException
     * @throws RuntimeException
     */
    public function getStat(string $forFile): ?array
    {
        if (!$this->isOpen) {
            $this->openArchive();
        }

        if (false !== $stat = $this->archive->statName($forFile)) {
            return $stat;
        }

        return null;
    }

    /**
     * @inheritDoc
     * @return File
     */
    public function moveTo(BaseStorage $destination, ?int $constraints = null): static
    {
        // actual move file to file: use native functions if possible
        if (!$this->moveFileTo($destination, $constraints)) {
            throw new AccessDeniedException('unable to move file', 403);
        }

        return new File($destination);
    }
}
