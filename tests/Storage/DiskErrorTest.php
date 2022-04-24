<?php
declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Storage;

use League\Flysystem\FilesystemException;
use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\Exception;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;

class DiskErrorTest extends TestCase
{

    /**
     * @throws AccessDeniedException
     * @throws Exception
     * @throws \Exception
     */
    public function testImplicitReadError(): void
    {
        $file = new File(new Storage\Disk(__DIR__, 'non-existing-file.extension'));
        $this->expectException(FileNotFoundException::class);
        $file->getDate();
    }

    /**
     * @throws AccessDeniedException
     * @throws Exception
     * @throws \Exception
     */
    public function testImplicitReadConstraintError(): void
    {
        $file = new File(new Storage\Disk(__DIR__, '..', '_examples', 'test.txt'));
        $this->expectException(ConstraintsException::class);
        $file->getDate();
    }

    /**
     * @throws AccessDeniedException
     * @throws Exception
     * @throws \Exception
     */
    public function testImplicitDirectoryReadError(): void
    {
        $dir = new Directory(new Storage\Disk(__DIR__, 'non-existing-directory'));
        $this->expectException(FileNotFoundException::class);
        $dir->getDate();
    }

    /**
     * @throws AccessDeniedException
     * @throws Exception
     * @throws \Exception
     */
    public function testImplicitDirectoryReadConstraintError(): void
    {
        $dir = new Directory(new Storage\Disk(__DIR__, '..', '_examples'));
        $this->expectException(ConstraintsException::class);
        $dir->getDate();
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws FilesystemException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function testImplicitDirectoryWriteError(): void
    {
        $dir = new Directory(new Storage\Disk(__DIR__, 'non-existing-directory'));
        $this->expectException(FileNotFoundException::class);
        $dir->remove();
    }

    /**
     * @throws AccessDeniedException
     * @throws Exception
     * @throws FilesystemException
     */
    public function testImplicitDirectoryWriteConstraintError(): void
    {
        $dir = new Directory(new Storage\Disk(__DIR__, '..', '_examples'));
        $this->expectException(ConstraintsException::class);
        $dir->remove();
    }
}
