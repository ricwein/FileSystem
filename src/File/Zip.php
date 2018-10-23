<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\File;

use ZipArchive;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\RuntimeException;
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
    const ZIP_ERRORS = [
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
    const ZIP_ENCRYPTION_ALG = ZipArchive::EM_AES_256;

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
            if (array_key_exists($result, static::ZIP_ERRORS, true)) {
                throw new RuntimeException(sprintf('[%d] Error while opening ZipArchive: "%s"', $result, static::ZIP_ERRORS[$result]), 500);
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
     * @param Directory $directory
     * @param  callable|null $filter DirectoryIterator-Filter in format: function(Storage $file): bool
     * @return self
     */
    public function addDirectory(Directory $directory, ?callable $filter = null): self
    {
        return $this->addDirectoryStorage($directory->storage(), $filter);
    }

    /**
     * @param Storage $storage
     * @param  callable|null $filter DirectoryIterator-Filter in format: function(Storage $file): bool
     * @return self
     */
    public function addDirectoryStorage(Storage $storage, ?callable $filter = null): self
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
        return $this->addFileStorage($file->storage(), $name);
    }

    /**
     * @param Storage $storage
     * @param string|null $name
     * @throws UnexpectedValueException
     * @throws RuntimeException
     * @return self
     */
    public function addFileStorage(Storage $storage, ?string $name = null): self
    {
        if (!$this->isOpen) {
            $this->openArchive();
        }

        switch (true) {
            case $storage instanceof Storage\Disk: $name = $this->addFileFromDisk($storage, $name); break;
            case $storage instanceof Storage\Memory: $name = $this->addFileFromMemory($storage, $name); break;
            case $storage instanceof Storage\Flysystem: $name = $this->addFileFromFlysystem($storage, $name); break;
            default: throw new UnexpectedValueException(sprintf('invalid type for 1 Argument in %s(), expected instance of Storage or File, but "%s" given', __METHOD__, is_object($storage) ? get_class($storage) : gettype($storage)), 500);
        }

        if ($this->password !== null && !$this->archive->setEncryptionName($name, static::ZIP_ENCRYPTION_ALG, $this->password)) {
            throw new RuntimeException(sprintf('failed to encrypt File "%s"', $name), 500);
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
