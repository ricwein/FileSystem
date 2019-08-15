<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Helper;

use PHPUnit\Framework\TestCase;
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

    /**
     * @return void
     */
    public function testStrings()
    {
        $file = new File(PathFinder::try([
            __FILE__, '.notExistingFilename',
            __FILE__,
        ]));

        $this->assertTrue($file->isFile());
        $this->assertSame($file->path()->real, __FILE__);
    }

    /**
     * @return void
     */
    public function testPaths()
    {
        $file = new File(PathFinder::try([
            new Path([__FILE__, '.notExistingFilename']),
            new Path([__FILE__]),
        ]));

        $this->assertTrue($file->isFile());
        $this->assertSame($file->path()->real, __FILE__);
    }

    /**
     * @return void
     */
    public function testStorages()
    {
        $file = new File(PathFinder::try([
            new Storage\Disk(__FILE__, '.notExistingFilename'),
            new Storage\Disk(__FILE__),
        ]));

        $this->assertTrue($file->isFile());
        $this->assertSame($file->path()->real, __FILE__);
    }

    /**
     * @return void
     */
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

    /**
     * @return void
     */
    public function testFiles()
    {
        $file = new File(PathFinder::try([
            new File(new Storage\Disk(__FILE__, '.notExistingFilename')),
            new File(new Storage\Disk(__FILE__)),
        ]));

        $this->assertTrue($file->isFile());
        $this->assertSame($file->path()->real, __FILE__);
    }

    /**
     * @expectedException \ricwein\FileSystem\Exceptions\FileNotFoundException
     * @return void
     */
    public function testErrors()
    {
        $file = new File(PathFinder::try([
            new Path([__FILE__, '.notExistingFilename']),
            new File(new Storage\Disk(__FILE__, '.notExistingFilename')),
            new File(new Storage\Disk(__DIR__, '.notExistingFilename')),
        ]));
    }
}
