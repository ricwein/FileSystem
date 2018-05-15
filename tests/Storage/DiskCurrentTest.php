<?php declare(strict_types = 1);

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;

/**
 * test Temp-Storage
 *
 * @author Richard Weinhold
 */
class DiskCurrentTest extends TestCase
{
    /**
     * @return void
     */
    public function testFileOpen()
    {
        $cwdFile = new File(new Storage\Disk\Current('tests/Storage', basename(__FILE__)));
        $file = new File(new Storage\Disk('tests/Storage', basename(__FILE__)));

        $this->assertTrue($cwdFile->isFile());
        $this->assertTrue($file->isFile());

        $this->assertNotSame($file->path()->raw, $cwdFile->path()->raw);
    }
}
