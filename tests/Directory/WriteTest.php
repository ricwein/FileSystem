<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Directory;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Storage;

class WriteTest extends TestCase
{
    /**
     * @throws AccessDeniedException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testCreateDir(): void
    {
        $dir = new Directory(new Storage\Disk\Temp());
        self::assertFileExists($dir->path()->raw);
        self::assertFileExists($dir->path()->real);
        self::assertDirectoryExists($dir->path()->real);
    }

    /**
     * @throws AccessDeniedException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws ConstraintsException
     */
    public function testCreateRecursiveDir(): void
    {
        $dir1 = new Directory(new Storage\Disk\Temp());
        $dir2 = new Directory(new Storage\Disk($dir1, 'dir2'), Constraint::STRICT);
        $dir3 = new Directory(new Storage\Disk($dir1, 'dir3/dir4/dir5'), Constraint::STRICT);

        $dir2->mkdir();
        $dir3->mkdir();

        foreach ([$dir1, $dir2, $dir3] as $dir) {
            self::assertFileExists($dir->path()->raw);
            self::assertFileExists($dir->path()->real);
            self::assertDirectoryExists($dir->path()->real);
        }

        self::assertSame('/dir2', str_replace($dir1->path()->real, '', $dir2->path()->real));
        self::assertSame('/dir3/dir4/dir5', str_replace($dir1->path()->real, '', $dir3->path()->real));
    }

    /**
     * @throws AccessDeniedException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testTempDirRemoval(): void
    {
        $tmpDir = new Directory(new Storage\Disk\Temp());

        self::assertFileExists($tmpDir->path()->raw);
        self::assertFileExists($tmpDir->path()->real);
        self::assertDirectoryExists($tmpDir->path()->real);

        $path = $tmpDir->path()->raw;
        $tmpDir = null;

        self::assertFileDoesNotExist($path);
    }
}
