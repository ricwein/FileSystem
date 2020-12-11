<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\File;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\Exception;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Helper\Constraint;

class CopyTest extends TestCase
{
    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
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
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
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
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
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
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
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
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testCopyToDir(): void
    {
        $source = new File(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $destination = new Directory(new Storage\Disk\Temp());

        self::assertTrue($destination->isDir());
        self::assertTrue($destination->storage()->isDir());

        $retFile = $source->copyTo($destination->storage());

        self::assertSame($source->path()->filename, $retFile->path()->filename);
        self::assertSame($destination->path()->raw, $retFile->path()->directory);
        self::assertStringEqualsFile(__DIR__ . '/../_examples/test.txt', $retFile->read());
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testCopyMemoryToDir(): void
    {
        $source = new File(new Storage\Memory(file_get_contents(__DIR__ . '/../_examples/test.txt')));
        $destination = new Directory(new Storage\Disk\Temp());

        self::assertTrue($destination->isDir());
        self::assertTrue($destination->storage()->isDir());

        $retFile = $source->copyTo($destination->storage());

        self::assertNotFalse(strpos($retFile->path()->filename, '.file'));
        self::assertSame($destination->path()->raw, $retFile->path()->directory);
        self::assertStringEqualsFile(__DIR__ . '/../_examples/test.txt', $retFile->read());
    }
}
