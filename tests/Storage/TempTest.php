<?php declare(strict_types = 1);

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;

/**
 * test Temp-Storage
 *
 * @author Richard Weinhold
 */
class TempTest extends TestCase
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
}
