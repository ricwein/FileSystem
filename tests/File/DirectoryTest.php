<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\File;

use League\Flysystem\FilesystemException;
use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;

class DirectoryTest extends TestCase
{
    /**
     * @throws FilesystemException
     */
    public function testSinglePath(): void
    {
        $file = new File(new Storage\Disk(__FILE__));
        $dir = $file->dir();

        self::assertTrue($file->isValid());
        self::assertTrue($dir->isValid());

        self::assertSame($file->getPath()->getDirectory(), $dir->getPath()->getRealPath());
        self::assertSame(__DIR__, $dir->getPath()->getRealPath());
        self::assertSame(dirname($file->getPath()->getRealPath()), $dir->getPath()->getRealPath());
    }

    /**
     * @throws FilesystemException
     */
    public function testTwoPartedPath(): void
    {
        $sDir = realpath(__DIR__ . '/../../');
        $sFile = str_replace($sDir, '', __FILE__);

        $file = new File(new Storage\Disk(__DIR__ . '/../../', $sFile));

        $dir = $file->dir();

        self::assertTrue($file->isValid());
        self::assertTrue($dir->isValid());

        self::assertSame(realpath($file->getPath()->getDirectory()), $dir->getPath()->getRealPath());
        self::assertSame(__DIR__, $dir->getPath()->getRealPath());
        self::assertSame(dirname($file->getPath()->getRealPath()), $dir->getPath()->getRealPath());
    }

    /**
     * @throws FilesystemException
     */
    public function testThreePartedPath(): void
    {
        $sDir1 = realpath(__DIR__ . '/../');
        $sDir2 = str_replace($sDir1, '', __DIR__);
        $sFile = basename(__FILE__);

        $file = new File(new Storage\Disk($sDir1, $sDir2, $sFile));

        $dir = $file->dir();

        self::assertTrue($file->isValid());
        self::assertTrue($dir->isValid());

        self::assertSame(realpath($file->getPath()->getDirectory()), $dir->getPath()->getRealPath());
        self::assertSame(__DIR__, $dir->getPath()->getRealPath());
        self::assertSame(dirname($file->getPath()->getRealPath()), $dir->getPath()->getRealPath());
    }
}
