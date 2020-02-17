<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Directory;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\ConstraintsException;
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
    public function testDirChange()
    {
        $dir = new Directory(new Storage\Disk(__DIR__ . '/../'));
        $this->assertSame($dir->path()->real, realpath(__DIR__ . '/../'));

        $dir->cd('_examples');
        $this->assertSame($dir->path()->real, realpath(__DIR__ . '/../_examples/'));
    }

    public function testDirUp()
    {
        $dir = new Directory(new Storage\Disk(__DIR__));
        $this->assertSame($dir->path()->real, realpath(__DIR__));

        $dir->up(2);
        $this->assertSame($dir->path()->real, realpath(__DIR__ . '/../../'));
    }

    public function testFileRead()
    {
        $dir = new Directory(new Storage\Disk(__DIR__), Constraint::LOOSE);

        $this->assertSame(
            $dir->up(2)->file('LICENSE')->read(),
            file_get_contents(__DIR__ . '/../../LICENSE')
        );
    }

    public function testDirUpError()
    {
        $dir = new Directory(new Storage\Disk(__DIR__));
        $this->assertSame($dir->path()->real, realpath(__DIR__));

        $this->expectException(ConstraintsException::class);
        $this->expectExceptionMessageMatches('/.*constraint failed: the given path.*is not within the safepath.*/');

        $dir->up(2);
        $dir->file('LICENSE')->read();
    }
}
