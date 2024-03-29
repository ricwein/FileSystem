<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Directory;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\FileSystem;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Storage\BaseStorage;
use SplFileInfo;

class ReadTest extends TestCase
{
    /**
     * @return array
     */
    protected function listTestfiles(): array
    {
        $path = __DIR__ . '/../_examples';

        return array_filter(scandir($path), static function (string $filename) use ($path): bool {
            return (!str_starts_with($filename, '.')) && is_file($path . '/' . $filename);
        });
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function testListing(): void
    {
        $dir = new Directory(new Storage\Disk(__DIR__, '..', '_examples'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);

        /** @var Directory|File $file */
        foreach ($dir->list(true)->all() as $file) {

            // skip directories
            if ($file instanceof Directory) {
                continue;
            }

            self::assertTrue($file->isFile());

            self::assertInstanceOf(File::class, $file);
            self::assertInstanceOf(Storage\Disk::class, $file->storage());

            self::assertContains($file->getPath()->getFilename(), $this->listTestfiles());
        }
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function testListingFiles(): void
    {
        $dir = new Directory(new Storage\Disk(__DIR__, '..', '_examples'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);

        /** @var File $file */
        foreach ($dir->list(true)->files() as $file) {
            self::assertTrue($file->isFile());

            self::assertInstanceOf(File::class, $file);
            self::assertInstanceOf(Storage\Disk::class, $file->storage());

            self::assertContains($file->getPath()->getFilename(), $this->listTestfiles());
        }
    }

    /**
     * @throws AccessDeniedException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function testListingDirectories(): void
    {
        $dir = new Directory(new Storage\Disk(__DIR__, '..'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $path = $dir->getPath()->getRawPath();

        // dynamically fetch list of all directories which should be returned
        $shouldDirs = array_filter(scandir($path), static function (string $filename) use ($path): bool {
            return (!str_starts_with($filename, '.')) && is_dir($path . '/' . $filename);
        });


        /** @var Directory $dir */
        foreach ($dir->list()->dirs() as $dir) {
            self::assertTrue($dir->isDir());

            self::assertInstanceOf(Directory::class, $dir);
            self::assertInstanceOf(Storage\Disk::class, $dir->storage());

            self::assertContains($dir->getPath()->getBasename(), $shouldDirs);
        }
    }

    /**
     * @throws AccessDeniedException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function testListPathFilters(): void
    {
        $dir = new Directory(new Storage\Disk(__DIR__, '..', '_examples'), Constraint::LOOSE);

        $iterator = $dir->list();
        $iterator->filterPath(static function (SplFileInfo $file): bool {
            return $file->getSize() > 1024;
        });

        /** @var File $file */
        foreach ($iterator->all() as $file) {
            self::assertContains($file->getPath()->getFilename(), ['test.png', 'archive.zip', 'certificate.crt']);
        }
    }

    /**
     * @throws AccessDeniedException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function testListStorageFilters(): void
    {
        $dir = new Directory(new Storage\Disk(__DIR__, '..', '_examples'), Constraint::LOOSE);

        $iterator = $dir->list();
        $iterator->filterStorage(static function (BaseStorage $storage): bool {
            return $storage->getSize() > 1024;
        });

        /** @var File $file */
        foreach ($iterator->all() as $file) {
            self::assertContains($file->getPath()->getFilename(), ['test.png', 'archive.zip', 'certificate.crt']);
        }
    }

    /**
     * @throws AccessDeniedException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function testListFileSystemFilters(): void
    {
        $dir = new Directory(new Storage\Disk(__DIR__, '..', '_examples'), Constraint::LOOSE);

        $iterator = $dir->list();
        $iterator->filter(static function (FileSystem $file): bool {
            return $file->isFile() && ($file->getPath()->getExtension() === 'png');
        });

        /** @var File $file */
        foreach ($iterator->all() as $file) {
            self::assertSame($file->getPath()->getFilename(), 'test.png');
        }
    }
}
