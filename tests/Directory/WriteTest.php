<?php declare(strict_types = 1);

namespace ricwein\FileSystem\Tests\Directory;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Storage;

use ricwein\FileSystem\Helper\Path;

/**
 * test FileSyst\File bases
 *
 * @author Richard Weinhold
 */
class WriteTest extends TestCase
{
    /**
     * @return void
     */
    public function testCreateDir()
    {
        $dir = new Directory(new Storage\Disk\Temp());
        $this->assertTrue(file_exists($dir->path()->raw));
        $this->assertTrue(file_exists($dir->path()->real));
        $this->assertTrue(is_dir($dir->path()->real));
    }

    /**
     * @return void
     */
    public function testCreateRecursiveDir()
    {
        $dir1 = new Directory(new Storage\Disk\Temp());
        $dir2 = new Directory(new Storage\Disk($dir1, 'dir2'));
        $dir3 = new Directory(new Storage\Disk($dir1, 'dir3/dir4/dir5'));

        $dir2->create();
        $dir3->create();

        foreach ([$dir1, $dir2, $dir3] as $dir) {
            $this->assertTrue(file_exists($dir->path()->raw));
            $this->assertTrue(file_exists($dir->path()->real));
            $this->assertTrue(is_dir($dir->path()->real));
        }

        $this->assertSame('/dir2', str_replace($dir1->path()->real, '', $dir2->path()->real));
        $this->assertSame('/dir3/dir4/dir5', str_replace($dir1->path()->real, '', $dir3->path()->real));
    }

    /**
     * @return void
     */
    public function testTempDirRemoval()
    {
        $tmpDir = new Directory(new Storage\Disk\Temp());

        $this->assertTrue(file_exists($tmpDir->path()->raw));
        $this->assertTrue(file_exists($tmpDir->path()->real));
        $this->assertTrue(is_dir($tmpDir->path()->real));

        $path = $tmpDir->path()->raw;
        $tmpDir = null;

        $this->assertFalse(file_exists($path));
    }
}
