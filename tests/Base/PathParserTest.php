<?php declare(strict_types = 1);

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Storage\Disk\Path;

/**
 * test FileSyst\File bases
 *
 * @author Richard Weinhold
 */
class PathParser extends TestCase
{
    /**
     * @return void
     */
    public function testPathParsing()
    {
        $pathA = new Path([__DIR__ . '/../Examples', 'test.txt']);
        $pathB = new Path([__DIR__, '/../', 'Examples', 'test.txt']);

        $this->assertSame($pathA->real, $pathB->real);
        $this->assertSame($pathA->directory, $pathB->directory);
        $this->assertSame($pathA->raw, $pathB->raw);

        $this->assertSame($pathA->filename, $pathB->filename);
        $this->assertSame($pathA->basename, $pathB->basename);
        $this->assertSame($pathA->extension, $pathB->extension);

        $this->assertNotSame($pathA->savepath, $pathB->savepath);
        $this->assertNotSame($pathA->filepath, $pathB->filepath);
    }
}
