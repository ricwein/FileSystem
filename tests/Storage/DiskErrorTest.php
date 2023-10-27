<?php
declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Storage;

use Exception;
use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\FilesystemException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;

class DiskErrorTest extends TestCase
{

    /**
     * @throws FilesystemException
     * @throws Exception
     */
    public function testImplicitReadError(): void
    {
        $file = new File(new Storage\Disk(__DIR__, 'non-existing-file.extension'));
        $this->expectException(FileNotFoundException::class);
        $file->getDate();
    }

    /**
     * @throws FilesystemException
     * @throws Exception
     */
    public function testImplicitReadConstraintError(): void
    {
        $file = new File(new Storage\Disk(__DIR__, '..', '_examples', 'test.txt'));
        $this->expectException(ConstraintsException::class);
        $file->getDate();
    }

    /**
     * @throws FilesystemException
     * @throws Exception
     */
    public function testImplicitDirectoryReadError(): void
    {
        $dir = new Directory(new Storage\Disk(__DIR__, 'non-existing-directory'));
        $this->expectException(FileNotFoundException::class);
        $dir->getDate();
    }

    /**
     * @throws FilesystemException
     * @throws Exception
     */
    public function testImplicitDirectoryReadConstraintError(): void
    {
        $dir = new Directory(new Storage\Disk(__DIR__, '..', '_examples'));
        $this->expectException(ConstraintsException::class);
        $dir->getDate();
    }

    /**
     * @throws FilesystemException
     */
    public function testImplicitDirectoryWriteError(): void
    {
        $dir = new Directory(new Storage\Disk(__DIR__, 'non-existing-directory'));
        $this->expectException(FileNotFoundException::class);
        $dir->remove();
    }

    /**
     * @throws FilesystemException
     */
    public function testImplicitDirectoryWriteConstraintError(): void
    {
        $dir = new Directory(new Storage\Disk(__DIR__, '..', '_examples'));
        $this->expectException(ConstraintsException::class);
        $dir->remove();
    }
}
