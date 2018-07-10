<?php declare(strict_types = 1);

namespace ricwein\FileSystem\Tests\Storage;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Directory;

/**
 * test Temp-Storage
 *
 * @author Richard Weinhold
 */
class DiskTempTest extends TestCase
{
    /**
     * @return void
     */
    public function testFileCreation()
    {
        $file = new File(new Storage\Disk\Temp());
        $this->assertTrue($file->isFile());
    }

    /**
     * @return void
     */
    public function testFileDestruction()
    {
        $file = new File(new Storage\Disk\Temp());

        $path = $file->path()->real;
        $this->assertTrue($file->isFile());

        $file = null;
        $this->assertFalse(file_exists($path));
    }

    /**
     * @return void
     */
    public function testPrecedentFilename()
    {
        $file = new File(new Storage\Disk\Temp('test.file'));

        $this->assertSame($file->path()->basename, 'test.file');
        $this->assertSame($file->path()->directory, sys_get_temp_dir());
    }
    /**
     * @return void
     */
    public function testPrecedentDirname()
    {
        $file = new Directory(new Storage\Disk\Temp('test.dir'));

        $this->assertSame($file->path()->basename, 'test.dir');
        $this->assertSame($file->path()->directory, sys_get_temp_dir());
    }
}
