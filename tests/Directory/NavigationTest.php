<?php declare(strict_types = 1);

namespace ricwein\FileSystem\Tests\Directory;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Helper\Constraint;

/**
 * test FileSyst\File bases
 *
 * @author Richard Weinhold
 */
class NavigationTest extends TestCase
{
    /**
     * @return void
     */
    public function testDirChange()
    {
        $dir = new Directory(new Storage\Disk(__DIR__.'/../'));
        $this->assertSame($dir->path()->real, realpath(__DIR__.'/../'));

        $dir->cd('_examples');
        $this->assertSame($dir->path()->real, realpath(__DIR__.'/../_examples/'));
    }

    /**
     * @return void
     */
    public function testDirUp()
    {
        $dir = new Directory(new Storage\Disk(__DIR__));
        $this->assertSame($dir->path()->real, realpath(__DIR__));

        $dir->up(2);
        $this->assertSame($dir->path()->real, realpath(__DIR__ . '/../../'));
    }

    /**
     * @return void
     */
    public function testFileRead()
    {
        $dir = new Directory(new Storage\Disk(__DIR__), Constraint::LOOSE);

        $this->assertSame(
            $dir->up(2)->file('LICENSE')->read(),
            file_get_contents(__DIR__ . '/../../LICENSE')
        );
    }

    /**
     * @expectedException \ricwein\FileSystem\Exceptions\ConstraintsException
     * @return void
     */
    public function testDirUpError()
    {
        $dir = new Directory(new Storage\Disk(__DIR__));
        $this->assertSame($dir->path()->real, realpath(__DIR__));

        $dir->up(2);
        $dir->file('LICENSE')->read();
    }
}
