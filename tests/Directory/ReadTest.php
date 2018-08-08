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
     * @return array
     */
    protected function listTestfiles(): array
    {
        $path = __DIR__ . '/../_examples';

        return array_filter(scandir($path), function (string $filename) use ($path): bool {
            return (strpos($filename, '.') !== 0) && is_file($path . '/' . $filename);
        });
    }

    /**
     * @return void
     */
    public function testListing()
    {
        $dir = new Directory(new Storage\Disk(__DIR__, '..', '_examples'));

        /** @var Directory|File $file */
        foreach ($dir->list(true) as $file) {

            // skip directories
            if ($file instanceof Directory) {
                continue;
            }

            $this->assertTrue($file->isFile());

            $this->assertInstanceOf(File::class, $file);
            $this->assertInstanceOf(Storage\Disk::class, $file->storage());

            $this->assertContains($file->path()->filename, $this->listTestfiles());
        }
    }

    /**
     * @return void
     */
    public function testListingFiles()
    {
        $dir = new Directory(new Storage\Disk(__DIR__, '..', '_examples'));

        /** @var File $file */
        foreach ($dir->listFiles(true) as $file) {
            $this->assertTrue($file->isFile());

            $this->assertInstanceOf(File::class, $file);
            $this->assertInstanceOf(Storage\Disk::class, $file->storage());

            $this->assertContains($file->path()->filename, $this->listTestfiles());
        }
    }

    /**
     * @return void
     */
    public function testListingDirectories()
    {
        $dir = new Directory(new Storage\Disk(__DIR__, '..'));
        $path = $dir->path()->raw;

        // dynamically fetch list of all directories which should be returned
        $shouldDirs = array_filter(scandir($path), function (string $filename) use ($path): bool {
            return (strpos($filename, '.') !== 0) && is_dir($path . '/' . $filename);
        });


        /** @var Directory $dir */
        foreach ($dir->listDirs(false) as $dir) {
            $this->assertTrue($dir->isDir());

            $this->assertInstanceOf(Directory::class, $dir);
            $this->assertInstanceOf(Storage\Disk::class, $dir->storage());

            $this->assertContains($dir->path()->basename, $shouldDirs);
        }
    }
}
