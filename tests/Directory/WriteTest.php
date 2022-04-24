<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Directory;

use League\Flysystem\FilesystemException;
use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Storage;

class WriteTest extends TestCase
{
    /**
     * @throws AccessDeniedException
     * @throws UnsupportedException
     */
    public function testCreateDir(): void
    {
        $dir = new Directory(new Storage\Disk\Temp());
        self::assertFileExists($dir->getPath()->getRawPath());
        self::assertFileExists($dir->getPath()->getRealPath());
        self::assertDirectoryExists($dir->getPath()->getRealPath());
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     * @throws FilesystemException
     */
    public function testCreateRecursiveDir(): void
    {
        $dir1 = new Directory(new Storage\Disk\Temp());
        $dir2 = new Directory(new Storage\Disk($dir1, 'dir2'), Constraint::STRICT);
        $dir3 = new Directory(new Storage\Disk($dir1, 'dir3/dir4/dir5'), Constraint::STRICT);

        $dir2->mkdir();
        $dir3->mkdir();

        foreach ([$dir1, $dir2, $dir3] as $dir) {
            self::assertFileExists($dir->getPath()->getRawPath());
            self::assertFileExists($dir->getPath()->getRealPath());
            self::assertDirectoryExists($dir->getPath()->getRealPath());
        }

        self::assertSame('/dir2', str_replace($dir1->getPath()->getRealPath(), '', $dir2->getPath()->getRealPath()));
        self::assertSame('/dir3/dir4/dir5', str_replace($dir1->getPath()->getRealPath(), '', $dir3->getPath()->getRealPath()));
    }

    /**
     * @throws AccessDeniedException
     * @throws UnsupportedException
     */
    public function testTempDirRemoval(): void
    {
        $tmpDir = new Directory(new Storage\Disk\Temp());

        self::assertFileExists($tmpDir->getPath()->getRawPath());
        self::assertFileExists($tmpDir->getPath()->getRealPath());
        self::assertDirectoryExists($tmpDir->getPath()->getRealPath());

        $path = $tmpDir->getPath()->getRawPath();
        $tmpDir = null;

        self::assertFileDoesNotExist($path);
    }
}
