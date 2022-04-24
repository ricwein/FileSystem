<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Storage;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\Exception;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Directory;

/**
 * test Temp-Storage
 *
 * @author Richard Weinhold
 */
class DiskTempTest extends TestCase
{
    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     */
    public function testFileCreation(): void
    {
        $file = new File(new Storage\Disk\Temp());
        self::assertTrue($file->isFile());
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws RuntimeException
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
     * @throws AccessDeniedException
     * @throws Exception
     * @throws RuntimeException
     */
    public function testPrecedentFilename(): void
    {
        $file = new File(new Storage\Disk\Temp('test.file'));

        self::assertSame('test.file', $file->getPath()->getFilename());
        self::assertSame(realpath(sys_get_temp_dir()), $file->getPath()->getDirectory());
    }

    /**
     * @throws AccessDeniedException
     * @throws UnsupportedException
     */
    public function testPrecedentDirname(): void
    {
        $file = new Directory(new Storage\Disk\Temp('test.dir'));

        self::assertSame('test.dir', $file->getPath()->getBasename());
        self::assertSame(realpath(sys_get_temp_dir()), $file->getPath()->getDirectory());
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws \Exception
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
