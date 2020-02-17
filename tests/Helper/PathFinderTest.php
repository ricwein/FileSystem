<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Helper;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Helper\Path;
use ricwein\FileSystem\Helper\PathFinder;

/**
 * test FileSyst\File bases
 *
 * @author Richard Weinhold
 */
class PathFinderTest extends TestCase
{
    public function testStrings()
    {
        $current = __FILE__;
        $file = new File(PathFinder::try([
            "{$current}.notExistingFilename",
            $current,
        ]));

        $this->assertTrue($file->isFile());
        $this->assertSame($file->path()->real, $current);
    }

    public function testPaths()
    {
        $file = new File(PathFinder::try([
            new Path([__FILE__, '.notExistingFilename']),
            new Path([__FILE__]),
        ]));

        $this->assertTrue($file->isFile());
        $this->assertSame($file->path()->real, __FILE__);
    }

    public function testStorages()
    {
        $file = new File(PathFinder::try([
            new Storage\Disk(__FILE__, '.notExistingFilename'),
            new Storage\Disk(__FILE__),
        ]));

        $this->assertTrue($file->isFile());
        $this->assertSame($file->path()->real, __FILE__);
    }

    public function testTempPaths()
    {
        $file = new File(PathFinder::try([
            new File(new Storage\Disk(__FILE__, '.notExistingFilename')),
            new File(new Storage\Disk\Temp()),
        ]));

        $this->assertTrue($file->isFile());
        $this->assertTrue($file->storage() instanceof Storage\Disk\Temp);
        $this->assertSame($file->directory()->path()->real, realpath(sys_get_temp_dir()));
    }

    public function testFiles()
    {
        $file = new File(PathFinder::try([
            new File(new Storage\Disk(__FILE__, '.notExistingFilename')),
            new File(new Storage\Disk(__FILE__)),
        ]));

        $this->assertTrue($file->isFile());
        $this->assertSame($file->path()->real, __FILE__);
    }

    public function testErrors()
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage("file not found");

        new File(PathFinder::try([
            new Path([__FILE__, '.notExistingFilename']),
            new File(new Storage\Disk(__FILE__, '.notExistingFilename')),
            new File(new Storage\Disk(__DIR__, '.notExistingFilename')),
        ]));
    }
}
