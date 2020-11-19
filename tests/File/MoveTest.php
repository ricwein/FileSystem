<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\File;

use \League\Flysystem\FileExistsException as FlyFileExistsException;
use \League\Flysystem\FileNotFoundException as FlyFileNotFoundException;
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

class MoveTest extends TestCase
{
    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws FlyFileExistsException
     * @throws FlyFileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testMoveFromDiskToDisk(): void
    {
        $source = (new File(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::LOOSE))->copyTo(new Storage\Disk\Temp());
        $destination = $source->moveTo(new Storage\Disk\Temp);

        self::assertInstanceOf(Storage\Disk\Temp::class, $destination->storage());
        self::assertFalse($source->isFile());
        self::assertTrue($destination->isFile());
        self::assertStringEqualsFile(__DIR__ . '/../_examples/test.txt', $destination->read());
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws FlyFileExistsException
     * @throws FlyFileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testMoveFromDiskToMemory(): void
    {
        $source = (new File(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::LOOSE))->copyTo(new Storage\Disk\Temp());
        $destination = $source->moveTo(new Storage\Memory);

        self::assertInstanceOf(Storage\Memory::class, $destination->storage());
        self::assertFalse($source->isFile());
        self::assertTrue($destination->isFile());
        self::assertStringEqualsFile(__DIR__ . '/../_examples/test.txt', $destination->read());
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws FlyFileExistsException
     * @throws FlyFileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testMoveFromMemoryToDisk(): void
    {
        $source = new File(new Storage\Memory(file_get_contents(__DIR__ . '/../_examples/test.txt')));
        $destination = $source->moveTo(new Storage\Disk\Temp);

        self::assertInstanceOf(Storage\Disk\Temp::class, $destination->storage());
        self::assertFalse($source->isFile());
        self::assertStringEqualsFile(__DIR__ . '/../_examples/test.txt', $destination->read());
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws FlyFileExistsException
     * @throws FlyFileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testMoveFromMemoryToMemory(): void
    {
        $source = new File(new Storage\Memory(file_get_contents(__DIR__ . '/../_examples/test.txt')));
        $destination = $source->moveTo(new Storage\Memory);

        self::assertInstanceOf(Storage\Memory::class, $destination->storage());
        self::assertFalse($source->isFile());
        self::assertStringEqualsFile(__DIR__ . '/../_examples/test.txt', $destination->read());
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws FlyFileExistsException
     * @throws FlyFileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testMoveToDir(): void
    {
        $source = (new File(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::LOOSE))->copyTo(new Storage\Disk\Temp());
        $destination = new Directory(new Storage\Disk\Temp());

        self::assertTrue($destination->isDir());
        self::assertTrue($destination->storage()->isDir());

        $retFile = $source->moveTo($destination->storage());

        self::assertFalse($source->isFile());
        self::assertSame($destination->path()->raw, $retFile->path()->directory);
        self::assertStringEqualsFile(__DIR__ . '/../_examples/test.txt', $retFile->read());
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws FlyFileExistsException
     * @throws FlyFileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testMoveMemoryToDir(): void
    {
        $source = new File(new Storage\Memory(file_get_contents(__DIR__ . '/../_examples/test.txt')));
        $destination = new Directory(new Storage\Disk\Temp());

        self::assertTrue($destination->isDir());
        self::assertTrue($destination->storage()->isDir());

        $retFile = $source->moveTo($destination->storage());

        self::assertFalse($source->isFile());
        self::assertNotFalse(strpos($retFile->path()->filename, '.file'));
        self::assertSame($destination->path()->raw, $retFile->path()->directory);
        self::assertStringEqualsFile(__DIR__ . '/../_examples/test.txt', $retFile->read());
    }
}
