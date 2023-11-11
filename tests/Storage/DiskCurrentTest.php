<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Storage;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Exceptions\FilesystemException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Storage\Disk;

/**
 * test Temp-Storage
 *
 * @author Richard Weinhold
 */
class DiskCurrentTest extends TestCase
{
    /**
     * @throws FilesystemException
     */
    public function testFileOpen(): void
    {
        $cwdFile = new File(new Storage\Disk\Current('tests/Storage', basename(__FILE__)));
        $file = new File(new Storage\Disk('tests/Storage', basename(__FILE__)));

        self::assertTrue($cwdFile->isFile());
        self::assertTrue($file->isFile());

        self::assertNotSame($file->getPath()->getRawPath(), $cwdFile->getPath()->getRawPath());
    }

    /**
     * @throws FilesystemException
     */
    public function testRootDir(): void
    {
        $dir = new Directory(new Storage\Disk\Current('/'));
        self::assertSame('/', $dir->getPath()->getRealPath());
    }

    /**
     * @throws FilesystemException
     */
    public function testEmptyInit(): void
    {
        $current = new File(new Disk\Current());

        self::assertSame($current->getPath()->getRealPath(), getcwd());
    }
}
