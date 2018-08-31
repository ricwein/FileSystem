<?php declare(strict_types = 1);

namespace ricwein\FileSystem\Tests\Storage;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Directory;
use League\Flysystem\Adapter\Local;

/**
 * test FlySystem-Storage Adapter
 *
 * @author Richard Weinhold
 * @requires
 */
class FlysystemTest extends TestCase
{
    public function setUp()
    {
        if (!class_exists('League\Flysystem\Filesystem')) {
            $this->markTestSkipped('The required package "League\Flysystem" is not installed');
        }
    }

    /**
     * @return void
     */
    public function testFileRead()
    {
        $file = new File(new Storage\Flysystem(new Local(__DIR__.'/../_examples'), 'test.txt'));

        $this->assertTrue($file->isFile());
        $this->assertSame(
            $file->read(),
            file_get_contents(__DIR__ . '/../_examples/test.txt')
        );
    }

    /**
     * @return void
     */
    public function testDirectoryRead()
    {
        $dir = new Directory(new Storage\Flysystem(new Local(__DIR__.'/..'), '_examples'));
        $this->assertTrue($dir->isDir());

        $files = [];

        /** @var Directory|File $entry */
        foreach ($dir->list(false)->all() as $file) {

            // skip directories
            if ($file instanceof Directory) {
                continue;
            }

            $files[] = $file;

            $this->assertInstanceOf(File::class, $file);
            $this->assertInstanceOf(Storage\Flysystem::class, $file->storage());
        }

        foreach ($files as $file) {
            $this->assertTrue($file->isFile());
        }
    }
}
