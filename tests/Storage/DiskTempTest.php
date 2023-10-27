<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Storage;

use Exception;
use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Exceptions\FilesystemException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;

/**
 * test Temp-Storage
 *
 * @author Richard Weinhold
 */
class DiskTempTest extends TestCase
{
    /**
     * @throws FilesystemException
     */
    public function testFileCreation(): void
    {
        $file = new File(new Storage\Disk\Temp());
        self::assertTrue($file->isFile());
    }

    /**
     * @throws FilesystemException
     */
    public function testFileDestruction(): void
    {
        $file = new File(new Storage\Disk\Temp());

        $path = $file->getPath()->getRealPath();
        self::assertTrue($file->isFile());

        $file = null;
        self::assertFileDoesNotExist($path);
    }

    /**
     * @throws FilesystemException
     */
    public function testPrecedentFilename(): void
    {
        $file = new File(new Storage\Disk\Temp('test.file'));

        self::assertSame('test.file', $file->getPath()->getFilename());
        self::assertSame(realpath(sys_get_temp_dir()), $file->getPath()->getDirectory());
    }

    /**
     * @throws FilesystemException
     */
    public function testPrecedentDirname(): void
    {
        $file = new Directory(new Storage\Disk\Temp('test.dir'));

        self::assertSame('test.dir', $file->getPath()->getBasename());
        self::assertSame(realpath(sys_get_temp_dir()), $file->getPath()->getDirectory());
    }

    /**
     * @throws FilesystemException
     * @throws Exception
     */
    public function testAbsolutePath(): void
    {
        $filename = bin2hex(random_bytes(32));
        $path = __DIR__ . '/' . $filename;

        $file = new File(new Storage\Disk(__DIR__, $filename));
        $file->removeOnFree()->touch();

        self::assertFileExists($path);
        self::assertTrue(is_file($path));

        self::assertSame($file->getPath()->getBasename(), $filename);
        self::assertSame($file->getPath()->getDirectory(), __DIR__);
        self::assertSame($file->getPath()->getRealPath(), $path);

        $file = null;
        self::assertFileDoesNotExist($path);
        self::assertFalse(is_file($path));
    }
}
