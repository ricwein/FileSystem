<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\File;

use ZipArchive;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Helper\MimeType;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Helper\DirectoryIterator;
use ricwein\FileSystem\Storage;

/**
 * represents a zip-file,
 * utilizing php's ZipArchive-class
 */
class Zip extends File
{
    /**
     * @var string[]
     */
    const ERROR_MESSAGES = [
        ZipArchive::ER_EXISTS => 'file already exists',
        ZipArchive::ER_INCONS => 'archive is inconsistent',
        ZipArchive::ER_INVAL => 'invalid argument',
        ZipArchive::ER_MEMORY => 'memory-malloc failure',
        ZipArchive::ER_NOENT => 'file not found',
        ZipArchive::ER_NOZIP => 'not a zip archive',
        ZipArchive::ER_OPEN => 'unable to open file',
        ZipArchive::ER_READ => 'unable to read file',
        ZipArchive::ER_SEEK => 'seek failed',
    ];

    /**
     * @var int
     */
    const ENCRYPTION_ALG = ZipArchive::EM_AES_256;

    /**
     * automatically close and reopend zip-archive after x files added,
     * to prevent running into file descriptors limit
     * @var int
     */
    const AUTO_COMMIT_AFTER = 128;

    /**
     * indicates whether the zip-archive is currently opened
     * @var bool
     */
    protected $isOpen = false;

    /**
     * @var ZipArchive
     */
    protected $archive;

    /**
     * @var int|null
     */
    protected $flags;

    /**
     * @var string|null
     */
    private $password = null;

    /**
     * @var int
     */
    private $filecounter = 0;

    /**
     * @inheritDoc
     * @param Storage\Disk $storage
     */
    public function __construct(Storage\Disk $storage, int $constraints = Constraint::STRICT, ?int $flags = ZipArchive::CREATE)
    {
        $this->flags = $flags;
        $this->archive = new ZipArchive();
        parent::__construct($storage, $constraints);
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
     * @return bool
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
     * @throws RuntimeException
     * @throws ConstraintsException
     * @return bool
     */
    protected function openArchive(): bool
    {
        if ($this->isOpen) {
            return false;
        }

        $result = $this->archive->open($this->path()->raw, $this->flags);

        // something went wrong, search and throw error-message
        if ($result !== true) {
            if (array_key_exists($result, static::ERROR_MESSAGES, true)) {
                throw new RuntimeException(sprintf('[%d] Error while opening ZipArchive: "%s"', $result, static::ERROR_MESSAGES[$result]), 500);
            }

            throw new RuntimeException(sprintf('[%d] Error while opening ZipArchive: "unknown"', $result), 500);
        }

        // validate zip-file contraints
        if (!$this->storage->doesSatisfyConstraints()) {
            $this->archive->close();
            throw $this->storage->getConstraintViolations();
        }

        if ($this->password !== null) {
            $this->archive->setPassword($this->password);
        }

        $this->path()->reload();

        $this->isOpen = true;
        return true;
    }

    /**
     * finish last running zip-transaction and close archive
     * @return self
     */
    public function commit(): self
    {
        $this->closeArchive();
        return $this;
    }

    /**
     * en/decrypt archive with given password
     * @param  string|null $password
     * @return self
     */
    public function withPassword(?string $password = null): self
    {
        $this->password = $password;

        if ($password !== null && $this->isOpen) {
            $this->archive->setPassword($password);
        }

        return $this;
    }

    /**
     * @param Storage\Disk   $destination
     * @param int|null       $constraints
     * @param  string[]|null $entries
     * @throws RuntimeException
     * @return Directory<Storage\Disk>
     */
    public function extractTo(Storage\Disk $destination, ?int $constraints = null, ?array $entries = null): Directory
    {
        if (!$this->isOpen) {
            $this->openArchive();
        }

        // validate directory-contraints
        if (!$destination->doesSatisfyConstraints()) {
            throw $destination->getConstraintViolations();
        }

        $destinationDir = new Directory($destination, ($constraints !== null) ? $constraints : $this->storage->getConstraints());

        // create destination dir
        if (!$destinationDir->isDir() && $destinationDir->storage()->isFile()) {
            $destinationDir->mkdir();
        }

        // try to extract archive (or selected entries only)
        $result = $this->archive->extractTo($destinationDir->path()->raw, $entries);

        if ($result !== true) {
            throw new RuntimeException(sprintf('unable to extract ZipArchive in path: "%s"%s', $destinationDir->path()->raw, $this->password === null ? '' : ', wrong password?'), 500);
        }

        return $destinationDir;
    }

    /**
     * adds directory to zip-archive
     * @param Directory $directory
     * @param string    $toNode adds content of directory to a sub-directory, or the root of the zip-archive
     * @param  callable|null $filter DirectoryIterator-Filter in format: function(Storage $file): bool
     * @return self
     */
    public function addDirectory(Directory $directory, string $toNode = '/', ?callable $filter = null): self
    {
        return $this->addDirectoryStorage($directory->storage(), $toNode, $filter);
    }

    /**
     * @param Storage $storage
     * @param string  $toNode adds content of directory to a sub-directory, or the root of the zip-archive
     * @param  callable|null $filter DirectoryIterator-Filter in format: function(Storage $file): bool
     * @return self
     */
    public function addDirectoryStorage(Storage $storage, string $toNode = '/', ?callable $filter = null): self
    {
        return $this->addDirectoryStorageContent($storage, rtrim($toNode, '/') . '/' . basename($storage->path()->raw) . '/', $filter);
    }

    /**
     * adds directory-content to zip-archive
     * @param Directory $directory
     * @param string    $toNode adds content of directory to a sub-directory, or the root of the zip-archive
     * @param  callable|null $filter DirectoryIterator-Filter in format: function(Storage $file): bool
     * @return self
     */
    public function addDirectoryContent(Directory $directory, string $toNode = '/', ?callable $filter = null): self
    {
        return $this->addDirectoryStorageContent($directory->storage(), $toNode, $filter);
    }

    /**
     * @param Storage $storage
     * @param string  $toNode adds content of directory to a sub-directory, or the root of the zip-archive
     * @param  callable|null $filter DirectoryIterator-Filter in format: function(Storage $file): bool
     * @return self
     */
    public function addDirectoryStorageContent(Storage $storage, string $toNode = '/', ?callable $filter = null): self
    {
        // prepare recursive directory-iterator
        $iterator = new DirectoryIterator($storage, true);
        if ($filter !== null) {
            $iterator->filterStorage($filter);
        }

        // list all files in directory
        /** @var Storage $fileStorage */
        foreach ($iterator->storages() as $fileStorage) {
            if ($fileStorage->isDir()) {
                continue;
            }

            // relative file-path for in-ziparchive-name:
            $filepath = str_ireplace((rtrim($storage->path()->real, '/') . '/'), '', $fileStorage->path()->real);
            // append given directory-name:
            $filepath = ltrim(trim($toNode, '/') . '/' . $filepath, '/');

            $this->addFileStorage($fileStorage, $filepath);
        }

        return $this;
    }

    /**
     * @param File $file
     * @param string|null $name
     * @throws UnexpectedValueException
     * @throws RuntimeException
     * @return self
     */
    public function addFile(File $file, ?string $name = null): self
    {
        if (!$file->isFile() || !$file->isReadable()) {
            throw new FileNotFoundException(sprintf('unable to open file: "%s"', $file->storage() instanceof Storage\Disk ? $file->path()->raw : get_class($file->storage())), 404);
        }
        return $this->addFileStorage($file->storage(), $name);
    }

    /**
     * @param Storage $storage
     * @param string|null $name
     * @throws UnexpectedValueException
     * @throws RuntimeException
     * @throws FileNotFoundException
     * @return self
     */
    public function addFileStorage(Storage $storage, ?string $name = null): self
    {
        if (!$this->isOpen) {
            $this->openArchive();
        }

        if (!$storage->isFile() || !$storage->isReadable()) {
            throw new FileNotFoundException(sprintf('unable to open file: "%s"', $storage instanceof Storage\Disk ? $storage->path()->raw : get_class($storage)), 404);
        }

        // add file to archive
        switch (true) {
            case $storage instanceof Storage\Disk: $name = $this->addFileFromDisk($storage, $name); break;
            case $storage instanceof Storage\Memory: $name = $this->addFileFromMemory($storage, $name); break;
            case $storage instanceof Storage\Flysystem: $name = $this->addFileFromFlysystem($storage, $name); break;
            default: throw new UnexpectedValueException(sprintf('invalid type for 1 Argument in %s(), expected instance of Storage or File, but "%s" given', __METHOD__, is_object($storage) ? get_class($storage) : gettype($storage)), 500);
        }

        // encrypt file in archive if password isset
        if ($this->password !== null && !$this->archive->setEncryptionName($name, static::ENCRYPTION_ALG, $this->password)) {
            throw new RuntimeException(sprintf('failed to encrypt File "%s"', $name), 500);
        }

        // run auto-commit after x files
        if (++$this->filecounter > static::AUTO_COMMIT_AFTER) {
            $this->commit();
            $this->filecounter = 0;
        }

        return $this;
    }

    /**
     * @param Storage\Disk $file
     * @param string|null $name
     * @throws RuntimeException
     * @return string filename in zip-archive
     */
    private function addFileFromDisk(Storage\Disk $file, ?string $name = null): string
    {
        $name = $name ?? $file->path()->filename;
        $name = str_replace(['\\', DIRECTORY_SEPARATOR], '/', $name);

        if (!$this->archive->addFile($file->path()->real, $name)) {
            throw new RuntimeException(sprintf('failed to add File "%s" from Disk (%s) to ZipArchive', $name, $file->path()->real), 500);
        }

        return $name;
    }

    /**
     * @param Storage\Memory $file
     * @param string|null $name
     * @throws RuntimeException
     * @return string filename in zip-archive
     */
    private function addFileFromMemory(Storage\Memory $file, ?string $name = null): string
    {
        // create new file-name
        if ($name === null) {
            $name = 'file.' . \bin2hex(\random_bytes(8));
            $mime = $file->getFileType();

            if (null !== $mime && null !== $extension = MimeType::getExtensionFor($mime)) {
                $name = $name . '.' . $extension;
            }
        }

        $name = str_replace(['\\', DIRECTORY_SEPARATOR], '/', $name);

        if (!$this->archive->addFromString($name, $file->readFile())) {
            throw new RuntimeException('failed to add File from Memory to ZipArchive', 500);
        }

        return $name;
    }

    /**
     * @param Storage\Flysystem $file
     * @param string|null $name
     * @throws RuntimeException
     * @return string filename in zip-archive
     */
    private function addFileFromFlysystem(Storage\Flysystem $file, ?string $name = null): string
    {
        $name = $name ?? basename($file->path());
        $name = str_replace(['\\', DIRECTORY_SEPARATOR], '/', $name);

        if (!$this->archive->addFromString($name, $file->readFile())) {
            throw new RuntimeException('failed to add File from Flysystem to ZipArchive', 500);
        }

        return $name;
    }

    /**
     * get archive status
     * @return string
     */
    public function getStatus(): string
    {
        if (!$this->isOpen) {
            $this->openArchive();
        }
        return $this->archive->getStatusString();
    }
}
