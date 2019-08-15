<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\File;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Helper\Constraint;

/**
 * test FileSyst\File bases
 *
 * @author Richard Weinhold
 */
class MoveTest extends TestCase
{
    /**
     * @return void
     */
    public function testMoveFromDiskToDisk()
    {
        $source = (new File(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::LOOSE))->copyTo(new Storage\Disk\Temp());
        $destination = $source->moveTo(new Storage\Disk\Temp);

        $this->assertInstanceOf(Storage\Disk\Temp::class, $destination->storage());
        $this->assertFalse($source->isFile());
        $this->assertTrue($destination->isFile());
        $this->assertSame(file_get_contents(__DIR__ . '/../_examples/test.txt'), $destination->read());
    }

    /**
     * @return void
     */
    public function testMoveFromDiskToMemory()
    {
        $source = (new File(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::LOOSE))->copyTo(new Storage\Disk\Temp());
        $destination = $source->moveTo(new Storage\Memory);

        $this->assertInstanceOf(Storage\Memory::class, $destination->storage());
        $this->assertFalse($source->isFile());
        $this->assertTrue($destination->isFile());
        $this->assertSame(file_get_contents(__DIR__ . '/../_examples/test.txt'), $destination->read());
    }

    /**
     * @return void
     */
    public function testMoveFromMemoryToDisk()
    {
        $source = new File(new Storage\Memory(file_get_contents(__DIR__ . '/../_examples/test.txt')));
        $destination = $source->moveTo(new Storage\Disk\Temp);

        $this->assertInstanceOf(Storage\Disk\Temp::class, $destination->storage());
        $this->assertFalse($source->isFile());
        $this->assertSame(file_get_contents(__DIR__ . '/../_examples/test.txt'), $destination->read());
    }

    /**
     * @return void
     */
    public function testMoveFromMemoryToMemory()
    {
        $source = new File(new Storage\Memory(file_get_contents(__DIR__ . '/../_examples/test.txt')));
        $destination = $source->moveTo(new Storage\Memory);

        $this->assertInstanceOf(Storage\Memory::class, $destination->storage());
        $this->assertFalse($source->isFile());
        $this->assertSame(file_get_contents(__DIR__ . '/../_examples/test.txt'), $destination->read());
    }

    /**
     * @return void
     */
    public function testMoveToDir()
    {
        $source = (new File(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::LOOSE))->copyTo(new Storage\Disk\Temp());
        $destination = new Directory(new Storage\Disk\Temp());

        $this->assertTrue($destination->isDir());
        $this->assertTrue($destination->storage()->isDir());

        $retFile = $source->moveTo($destination->storage());

        $this->assertFalse($source->isFile());
        $this->assertSame($destination->path()->raw, $retFile->path()->directory);
        $this->assertSame(file_get_contents(__DIR__ . '/../_examples/test.txt'), $retFile->read());
    }

    /**
     * @return void
     */
    public function testMoveMemoryToDir()
    {
        $source = new File(new Storage\Memory(file_get_contents(__DIR__ . '/../_examples/test.txt')));
        $destination = new Directory(new Storage\Disk\Temp());

        $this->assertTrue($destination->isDir());
        $this->assertTrue($destination->storage()->isDir());

        $retFile = $source->moveTo($destination->storage());

        $this->assertFalse($source->isFile());
        $this->assertTrue(strpos($retFile->path()->filename, '.file') !== false);
        $this->assertSame($destination->path()->raw, $retFile->path()->directory);
        $this->assertSame(file_get_contents(__DIR__ . '/../_examples/test.txt'), $retFile->read());
    }
}
