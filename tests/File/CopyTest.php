<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\File;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Helper\Constraint;

/**
 * test FileSystem\File bases
 *
 * @author Richard Weinhold
 */
class CopyTest extends TestCase
{
    /**
     * @return void
     */
    public function testCopyFromDiskToDisk()
    {
        $source = new File(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $destination = $source->copyTo(new Storage\Disk\Temp());

        $this->assertInstanceOf(Storage\Disk::class, $destination->storage());
        $this->assertInstanceOf(Storage\Disk\Temp::class, $destination->storage());
        $this->assertTrue($destination->isFile());

        $this->assertSame(
            $source->read(),
            $destination->read()
        );
        $this->assertSame(file_get_contents(__DIR__ . '/../_examples/test.txt'), $destination->read());
    }

    /**
     * @return void
     */
    public function testCopyFromDiskToMemory()
    {
        $source = new File(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $destination = $source->copyTo(new Storage\Memory());

        $this->assertInstanceOf(Storage\Memory::class, $destination->storage());

        $this->assertSame(
            $source->read(),
            $destination->read()
        );
        $this->assertSame(file_get_contents(__DIR__ . '/../_examples/test.txt'), $destination->read());
    }

    /**
     * @return void
     */
    public function testCopyFromMemoryToDisk()
    {
        $source = new File(new Storage\Memory(file_get_contents(__DIR__ . '/../_examples/test.txt')));
        $destination = $source->copyTo(new Storage\Disk\Temp());

        $this->assertInstanceOf(Storage\Disk\Temp::class, $destination->storage());
        $this->assertInstanceOf(Storage\Disk::class, $destination->storage());
        $this->assertSame($source->read(), $destination->read());
        $this->assertSame(file_get_contents(__DIR__ . '/../_examples/test.txt'), $destination->read());
    }

    /**
     * @return void
     */
    public function testCopyFromMemoryToMemory()
    {
        $source = new File(new Storage\Memory(file_get_contents(__DIR__ . '/../_examples/test.txt')));
        $destination = $source->copyTo(new Storage\Memory());

        $this->assertInstanceOf(Storage\Memory::class, $destination->storage());

        $this->assertSame($source->read(), $destination->read());
        $this->assertSame(file_get_contents(__DIR__ . '/../_examples/test.txt'), $destination->read());
    }

    /**
     * @return void
     */
    public function testCopyToDir()
    {
        $source = new File(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $destination = new Directory(new Storage\Disk\Temp());

        $this->assertTrue($destination->isDir());
        $this->assertTrue($destination->storage()->isDir());

        $retFile = $source->copyTo($destination->storage());

        $this->assertSame($source->path()->filename, $retFile->path()->filename);
        $this->assertSame($destination->path()->raw, $retFile->path()->directory);
        $this->assertSame(file_get_contents(__DIR__ . '/../_examples/test.txt'), $retFile->read());
    }

    /**
     * @return void
     */
    public function testCopyMemoryToDir()
    {
        $source = new File(new Storage\Memory(file_get_contents(__DIR__ . '/../_examples/test.txt')));
        $destination = new Directory(new Storage\Disk\Temp());

        $this->assertTrue($destination->isDir());
        $this->assertTrue($destination->storage()->isDir());

        $retFile = $source->copyTo($destination->storage());

        $this->assertTrue(strpos($retFile->path()->filename, '.file') !== false);
        $this->assertSame($destination->path()->raw, $retFile->path()->directory);
        $this->assertSame(file_get_contents(__DIR__ . '/../_examples/test.txt'), $retFile->read());
    }
}
