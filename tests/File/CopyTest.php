<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\File;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Exceptions\FilesystemException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Storage;

class CopyTest extends TestCase
{
    /**
     * @throws FilesystemException
     */
    public function testCopyFromDiskToDisk(): void
    {
        $source = new File(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $destination = $source->copyTo(new Storage\Disk\Temp());

        self::assertInstanceOf(Storage\Disk::class, $destination->storage());
        self::assertInstanceOf(Storage\Disk\Temp::class, $destination->storage());
        self::assertTrue($destination->isFile());

        self::assertSame(
            $source->read(),
            $destination->read()
        );
        self::assertStringEqualsFile(__DIR__ . '/../_examples/test.txt', $destination->read());
    }

    /**
     * @throws FilesystemException
     */
    public function testCopyFromDiskToMemory(): void
    {
        $source = new File(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $destination = $source->copyTo(new Storage\Memory());

        self::assertInstanceOf(Storage\Memory::class, $destination->storage());

        self::assertSame(
            $source->read(),
            $destination->read()
        );
        self::assertStringEqualsFile(__DIR__ . '/../_examples/test.txt', $destination->read());
    }

    /**
     * @throws FilesystemException
     */
    public function testCopyFromMemoryToDisk(): void
    {
        $source = new File(new Storage\Memory(file_get_contents(__DIR__ . '/../_examples/test.txt')));
        $destination = $source->copyTo(new Storage\Disk\Temp());

        self::assertInstanceOf(Storage\Disk\Temp::class, $destination->storage());
        self::assertInstanceOf(Storage\Disk::class, $destination->storage());
        self::assertSame($source->read(), $destination->read());
        self::assertStringEqualsFile(__DIR__ . '/../_examples/test.txt', $destination->read());
    }

    /**
     * @throws FilesystemException
     */
    public function testCopyFromMemoryToMemory(): void
    {
        $source = new File(new Storage\Memory(file_get_contents(__DIR__ . '/../_examples/test.txt')));
        $destination = $source->copyTo(new Storage\Memory());

        self::assertInstanceOf(Storage\Memory::class, $destination->storage());

        self::assertSame($source->read(), $destination->read());
        self::assertStringEqualsFile(__DIR__ . '/../_examples/test.txt', $destination->read());
    }

    /**
     * @throws FilesystemException
     */
    public function testCopyToDir(): void
    {
        $source = new File(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $destination = new Directory(new Storage\Disk\Temp());

        self::assertTrue($destination->isDir());
        self::assertTrue($destination->storage()->isDir());

        $retFile = $source->copyTo($destination->storage());

        self::assertSame($source->getPath()->getFilename(), $retFile->getPath()->getFilename());
        self::assertSame($destination->getPath()->getRealPath(), $retFile->getPath()->getDirectory());
        self::assertStringEqualsFile(__DIR__ . '/../_examples/test.txt', $retFile->read());
    }

    /**
     * @throws FilesystemException
     */
    public function testCopyMemoryToDir(): void
    {
        $source = new File(new Storage\Memory(file_get_contents(__DIR__ . '/../_examples/test.txt')));
        $destination = new Directory(new Storage\Disk\Temp());

        self::assertTrue($destination->isDir());
        self::assertTrue($destination->storage()->isDir());

        $retFile = $source->copyTo($destination->storage());

        self::assertNotFalse(strpos($retFile->getPath()->getFilename(), '.file'));
        self::assertSame($destination->getPath()->getRealPath(), $retFile->getPath()->getDirectory());
        self::assertStringEqualsFile(__DIR__ . '/../_examples/test.txt', $retFile->read());
    }
}
