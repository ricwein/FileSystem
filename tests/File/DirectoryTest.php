<?php declare(strict_types = 1);

namespace ricwein\FileSystem\Tests\File;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Helper\Constraint;

/**
 * test FileSystem\File bases
 *
 * @author Richard Weinhold
 */
class DirectoryTest extends TestCase
{
    /**
     * @return void
     */
    public function testSinglePath()
    {
        $file = new File(new Storage\Disk(__FILE__));
        $dir = $file->directory();

        $this->assertTrue($file->isValid());
        $this->assertTrue($dir->isValid());

        $this->assertSame($file->path()->directory, $dir->path()->real);
        $this->assertSame(__DIR__, $dir->path()->real);
        $this->assertSame(dirname($file->path()->real), $dir->path()->real);
    }

    /**
     * @return void
     */
    public function testTwoPartedPath()
    {
        $sdir = realpath(__DIR__ . '/../../');
        $sfile = str_replace($sdir, '', __FILE__);

        $file = new File(new Storage\Disk(__DIR__ . '/../../', $sfile));

        $dir = $file->directory();

        $this->assertTrue($file->isValid());
        $this->assertTrue($dir->isValid());

        $this->assertSame(realpath($file->path()->directory), $dir->path()->real);
        $this->assertSame(__DIR__, $dir->path()->real);
        $this->assertSame(dirname($file->path()->real), $dir->path()->real);
    }

    /**
     * @return void
     */
    public function testThreePartedPath()
    {
        $sdir1 = realpath(__DIR__ . '/../');
        $sdir2 = str_replace($sdir1, '', __DIR__);
        $sfile = basename(__FILE__);

        $file = new File(new Storage\Disk($sdir1, $sdir2, $sfile));

        $dir = $file->directory();

        $this->assertTrue($file->isValid());
        $this->assertTrue($dir->isValid());

        $this->assertSame(realpath($file->path()->directory), $dir->path()->real);
        $this->assertSame(__DIR__, $dir->path()->real);
        $this->assertSame(dirname($file->path()->real), $dir->path()->real);
    }
}
