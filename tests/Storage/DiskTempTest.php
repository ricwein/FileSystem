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

        $this->assertSame($file->path()->filename, 'test.file');
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

    /**
     * @return void
     */
    public function testAbsolutePath()
    {
        $filename = bin2hex(random_bytes(32));
        $path = __DIR__ . '/' . $filename;

        $file = new File(new Storage\Disk(__DIR__, $filename));
        $file->removeOnFree()->touch();

        $this->assertTrue(file_exists($path));
        $this->assertTrue(is_file($path));

        $this->assertSame($file->path()->basename, $filename);
        $this->assertSame($file->path()->directory, __DIR__);
        $this->assertSame($file->path()->real, $path);

        $file = null;
        $this->assertFalse(file_exists($path));
        $this->assertFalse(is_file($path));
    }
}
