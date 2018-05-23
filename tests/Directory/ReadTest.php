<?php declare(strict_types = 1);

namespace ricwein\FileSystem\Tests\Directory;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\File;

/**
 * test FileSyst\File bases
 *
 * @author Richard Weinhold
 */
class ReadTest extends TestCase
{
    /**
     * @expectedException \ricwein\FileSystem\Exceptions\UnexpectedValueException
     * @return void
     */
    public function testMemoryInit()
    {
        new Directory(new Storage\Memory());
    }

    /**
     * @return void
     */
    public function testListing()
    {
        $dir = new Directory(new Storage\Disk(__DIR__, '..', '_examples'));

        $files = [];
        foreach ($dir->list(false) as $file) {
            $files[$file->path()->filename] = $file;

            $this->assertInstanceOf(File::class, $file);
            $this->assertInstanceOf(Storage\Disk::class, $file->storage());
        }

        foreach ($files as $file) {
            $this->assertTrue($file->isFile());
        }
    }
}
