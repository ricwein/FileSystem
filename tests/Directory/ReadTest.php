<?php declare(strict_types = 1);

namespace ricwein\FileSystem\Tests\Directory;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Directory;

/**
 * test FileSyst\File bases
 *
 * @author Richard Weinhold
 */
class ReadTest extends TestCase
{
    /**
     * @expectedException \ricwein\FileSystem\Exceptions\UnexpectedValueException
     * @return void
     */
    public function testMemoryInit()
    {
        new Directory(new Storage\Memory());
    }
}
