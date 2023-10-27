<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Helper;

use League\Flysystem\FilesystemException as FlySystemException;
use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\FilesystemException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Helper\PathFinder;
use ricwein\FileSystem\Path;
use ricwein\FileSystem\Storage;

/**
 * @author Richard Weinhold
 */
class PathFinderTest extends TestCase
{
    /**
     * @throws FilesystemException
     */
    public function testStrings(): void
    {
        $current = __FILE__;
        $file = new File(
            PathFinder::try([
                "$current.notExistingFilename",
                $current,
            ])
        );

        self::assertTrue($file->isFile());
        self::assertSame($current, $file->getPath()->getRealPath());
    }

    /**
     * @throws FilesystemException
     */
    public function testPaths(): void
    {
        $file = new File(
            PathFinder::try([
                new Path(__FILE__, '.notExistingFilename'),
                new Path(__FILE__),
            ])
        );

        self::assertTrue($file->isFile());
        self::assertSame(__FILE__, $file->getPath()->getRealPath());
    }

    /**
     * @throws FilesystemException
     */
    public function testStorages(): void
    {
        $file = new File(
            PathFinder::try([
                new Storage\Disk(__FILE__, '.notExistingFilename'),
                new Storage\Disk(__FILE__),
            ])
        );

        self::assertTrue($file->isFile());
        self::assertSame(__FILE__, $file->getPath()->getRealPath());
    }

    /**
     * @throws FilesystemException
     * @throws FlySystemException
     */
    public function testTempPaths(): void
    {
        $file = new File(
            PathFinder::try([
                new File(new Storage\Disk(__FILE__, '.notExistingFilename')),
                new File(new Storage\Disk\Temp()),
            ])
        );

        self::assertTrue($file->isFile());
        self::assertInstanceOf(Storage\Disk\Temp::class, $file->storage());
        self::assertSame(realpath(sys_get_temp_dir()), $file->dir()->getPath()->getRealPath());
    }

    /**
     * @throws FilesystemException
     */
    public function testFiles(): void
    {
        $file = new File(
            PathFinder::try([
                new File(new Storage\Disk(__FILE__, '.notExistingFilename')),
                new File(new Storage\Disk(__FILE__)),
            ])
        );

        self::assertTrue($file->isFile());
        self::assertSame(realpath(__FILE__), $file->getPath()->getRealPath());
    }

    /**
     * @throws FilesystemException
     */
    public function testErrors(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage("file not found");

        new File(
            PathFinder::try([
                new Path(__FILE__, '.notExistingFilename'),
                new File(new Storage\Disk(__FILE__, '.notExistingFilename')),
                new File(new Storage\Disk(__DIR__, '.notExistingFilename')),
            ])
        );
    }
}
