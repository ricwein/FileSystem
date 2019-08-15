<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Helper;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\File;

use ricwein\FileSystem\Helper\Path;

use ricwein\FileSystem\Storage;

/**
 * test FileSyst\File bases
 *
 * @author Richard Weinhold
 */
class PathParserTest extends TestCase
{
    /**
     * @return void
     */
    public function testPathParsing()
    {
        $pathA = new Path([__DIR__ . '/../_examples', 'test.txt']);
        $pathB = new Path([__DIR__, '/../', '_examples', 'test.txt']);

        $this->assertSame($pathA->real, $pathB->real);
        $this->assertSame($pathA->directory, $pathB->directory);
        $this->assertSame($pathA->raw, $pathB->raw);

        $this->assertSame($pathA->filename, $pathB->filename);
        $this->assertSame($pathA->basename, $pathB->basename);
        $this->assertSame($pathA->extension, $pathB->extension);

        $this->assertNotSame($pathA->safepath, $pathB->safepath);
        $this->assertNotSame($pathA->filepath, $pathB->filepath);
    }

    /**
     * @return void
     */
    public function testPathSelfSimilar()
    {
        $path1 = new Path([__FILE__]);
        $path2 = new Path([$path1]);

        $this->assertSame($path1->getDetails(), $path2->getDetails());
    }

    /**
     * @return void
     */
    public function testMultiPathSelfSimilar()
    {
        $path1 = new Path([dirname(__FILE__), basename(__FILE__)]);
        $path2 = new Path([$path1]);

        $this->assertSame($path1->getDetails(), $path2->getDetails());
    }

    /**
     * @return void
     */
    public function testFilePathReusing()
    {
        $file1 = new File(new Storage\Disk(__FILE__));
        $file2 = new File(new Storage\Disk($file1->path()));

        $this->assertSame($file1->storage()->getDetails(), $file2->storage()->getDetails());
    }

    /**
     * @return void
     */
    public function testFileMultiPathReusing()
    {
        $file1 = new File(new Storage\Disk(dirname(__FILE__), basename(__FILE__)));
        $file2 = new File(new Storage\Disk($file1->path()));

        $this->assertSame($file1->storage()->getDetails(), $file2->storage()->getDetails());
    }
}
