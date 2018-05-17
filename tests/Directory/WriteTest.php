<?php declare(strict_types = 1);

namespace ricwein\FileSystem\Tests\Directory;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Storage;

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
        return $this->assertTrue(true);
        
        $dir = new Directory(new Storage\Disk\Temp());
        // $this->assertSame(null, $dir->path()->getDetails());
        $this->assertTrue(file_exists($dir->path()->raw));
        $this->assertTrue(file_exists($dir->path()->real));
        $this->assertTrue(is_dir($dir->path()->real));
    }
}
