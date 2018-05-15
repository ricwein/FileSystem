<?php declare(strict_types = 1);

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Helper\Path;

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
        $pathA = new Path([__DIR__ . '/../_examples', 'test.txt']);
        $pathB = new Path([__DIR__, '/../', '_examples', 'test.txt']);

        $this->assertSame($pathA->real, $pathB->real);
        $this->assertSame($pathA->directory, $pathB->directory);
        $this->assertSame($pathA->raw, $pathB->raw);

        $this->assertSame($pathA->filename, $pathB->filename);
        $this->assertSame($pathA->basename, $pathB->basename);
        $this->assertSame($pathA->extension, $pathB->extension);

        $this->assertNotSame($pathA->savepath, $pathB->savepath);
        $this->assertNotSame($pathA->filepath, $pathB->filepath);
    }

    /**
     * @return void
     */
    public function testPathRestrictions()
    {
        $path = new Path([realpath(__DIR__. '/../_examples'), 'test.txt']);

        $this->assertTrue($path->isSave(Path::NO_SYMLINK));
        $this->assertTrue($path->isSave(Path::IN_SAVEPATH));

        $path = new Path([__DIR__, '/../', '_examples', 'test.txt']);

        $this->assertTrue($path->isSave(Path::NO_SYMLINK));
        $this->assertFalse($path->isSave(Path::IN_SAVEPATH));

        $path = new Path([__FILE__]);
        $this->assertTrue($path->isSave(Path::NO_SYMLINK | Path::IN_SAVEPATH));
    }
}
