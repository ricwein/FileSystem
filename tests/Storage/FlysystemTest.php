<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Storage;

use League\Flysystem\FilesystemException as FlySystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\Exception;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Directory;
use League\Flysystem\Filesystem;

/**
 * test FlySystem-Storage Adapter
 *
 * @author Richard Weinhold
 * @requires
 */
class FlysystemTest extends TestCase
{
    public function setUp(): void
    {
        if (!class_exists(Filesystem::class)) {
            self::markTestSkipped('The required package "League\Flysystem" is not installed');
        }
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws FlySystemException
     */
    public function testFileRead(): void
    {
        $cmpFile = new File(new Storage\Disk(__DIR__, '/../_examples', 'test.txt'), Constraint::LOOSE);
        $file = new File(new Storage\Flysystem(new LocalFilesystemAdapter(__DIR__ . '/../_examples'), 'test.txt'));

        self::assertTrue($file->isFile());
        self::assertSame(
            $file->read(),
            file_get_contents(__DIR__ . '/../_examples/test.txt')
        );

        self::assertSame([
            'storage' => Storage\Flysystem::class,
            'path' => 'test.txt',
            'type' => 'text/plain',
            'timestamp' => $cmpFile->getTime(),
            'size' => $cmpFile->getSize(),
        ],
            $file->storage()->getDetails()
        );

        self::assertSame($cmpFile->getTime(), $file->getTime());
        self::assertSame($cmpFile->getHash(), $file->getHash());
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FlySystemException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function testDirectoryRead(): void
    {
        $dir = new Directory(new Storage\Flysystem(new LocalFilesystemAdapter(__DIR__ . '/..'), '_examples'));
        self::assertTrue($dir->isDir());

        $files = [];

        /** @var Directory|File $entry */
        foreach ($dir->list(false)->all() as $file) {

            // skip directories
            if ($file instanceof Directory) {
                continue;
            }

            $files[] = $file;

            self::assertInstanceOf(File::class, $file);
            self::assertInstanceOf(Storage\Flysystem::class, $file->storage());
        }

        foreach ($files as $file) {
            self::assertTrue($file->isFile());
        }
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws FlySystemException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function testNavigation(): void
    {
        $dir = new Directory(new Storage\Flysystem(new LocalFilesystemAdapter(__DIR__ . '/..'), '_examples'));
        self::assertTrue($dir->isDir());

        $file = $dir->file('archive.zip');
        self::assertTrue($file->isFile());
        self::assertFalse($file->isDir());
        self::assertSame($file->path(), '_examples/archive.zip');

        $dirAgain = $file->dir();
        self::assertTrue($dirAgain->isDir());
        self::assertFalse($dirAgain->isFile());
        self::assertSame($dirAgain->path(), '_examples');

        $parentDir = $dirAgain->up();
        self::assertTrue($parentDir->isDir());
        self::assertFalse($parentDir->isFile());
    }
}
