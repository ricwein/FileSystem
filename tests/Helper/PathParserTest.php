<?php declare(strict_types = 1);

namespace ricwein\FileSystem\Tests\Helper;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Helper\Path;

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
}
