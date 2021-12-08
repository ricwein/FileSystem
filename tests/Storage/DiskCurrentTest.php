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

use ricwein\FileSystem\Storage\Disk;

/**
 * test Temp-Storage
 *
 * @author Richard Weinhold
 */
class DiskCurrentTest extends TestCase
{
    /**
     * @return void
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testFileOpen(): void
    {
        $cwdFile = new File(new Storage\Disk\Current('tests/Storage', basename(__FILE__)));
        $file = new File(new Storage\Disk('tests/Storage', basename(__FILE__)));

        self::assertTrue($cwdFile->isFile());
        self::assertTrue($file->isFile());

        self::assertNotSame($file->path()->raw, $cwdFile->path()->raw);
    }

    /**
     * @throws AccessDeniedException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function testRootDir(): void
    {
        $dir = new Directory(new Storage\Disk\Current('/'));

        self::assertSame($dir->path()->real, '/');
    }

    /**
     * @throws AccessDeniedException
     * @throws Exception
     * @throws RuntimeException
     */
    public function testEmptyInit(): void
    {
        $current = new File(new Disk\Current());

        self::assertSame($current->path()->real, getcwd());
    }
}
