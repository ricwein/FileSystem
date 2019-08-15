<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Storage;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Directory;

use ricwein\FileSystem\Storage\Disk;

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

    /**
     * @return void
     */
    public function testRootDir()
    {
        $dir = new Directory(new Storage\Disk\Current('/'));

        $this->assertSame($dir->path()->real, '/');
    }

    /**
     * @return void
     */
    public function testEmptyInit()
    {
        $current = new File(new Disk\Current());

        $this->assertSame($current->path()->real, getcwd());
    }
}
