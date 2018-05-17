<?php declare(strict_types = 1);

namespace ricwein\FileSystem\Tests\File;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Helper\Constraint;

/**
 * test FileSyst\File bases
 *
 * @author Richard Weinhold
 */
class CopyTest extends TestCase
{
    /**
     * @return void
     */
    public function testSaveFromDiskToDisk()
    {
        $source = new File(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $destination = $source->saveAs(new Storage\Disk\Temp());

        $this->assertInstanceOf(Storage\Disk::class, $destination->storage());
        $this->assertInstanceOf(Storage\Disk\Temp::class, $destination->storage());
        $this->assertTrue($destination->isFile());

        $this->assertSame(
            $source->read(),
            $destination->read()
        );
        $this->assertSame(file_get_contents(__DIR__.'/../_examples/test.txt'), $destination->read());
    }

    /**
     * @return void
     */
    public function testSaveFromDiskToMemory()
    {
        $source = new File(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $destination = $source->saveAs(new Storage\Memory());

        $this->assertInstanceOf(Storage\Memory::class, $destination->storage());

        $this->assertSame(
            $source->read(),
            $destination->read()
        );
        $this->assertSame(file_get_contents(__DIR__.'/../_examples/test.txt'), $destination->read());
    }

    /**
     * @return void
     */
    public function testSaveFromMemoryToDisk()
    {
        $source = new File(new Storage\Memory(file_get_contents(__DIR__.'/../_examples/test.txt')));
        $destination = $source->saveAs(new Storage\Disk\Temp());

        $this->assertInstanceOf(Storage\Disk::class, $destination->storage());

        $this->assertSame($source->read(), $destination->read());
        $this->assertSame(file_get_contents(__DIR__.'/../_examples/test.txt'), $destination->read());
    }

    /**
     * @return void
     */
    public function testSaveFromMemoryToMemory()
    {
        $source = new File(new Storage\Memory(file_get_contents(__DIR__.'/../_examples/test.txt')));
        $destination = $source->saveAs(new Storage\Memory());

        $this->assertInstanceOf(Storage\Memory::class, $destination->storage());

        $this->assertSame($source->read(), $destination->read());
        $this->assertSame(file_get_contents(__DIR__.'/../_examples/test.txt'), $destination->read());
    }
}
