<?php declare(strict_types = 1);

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;

/**
 * test FileSyst\File bases
 *
 * @author Richard Weinhold
 */
class ReadFileTest extends TestCase
{
    /**
     * @return void
     */
    public function testFileRead()
    {
        $file = new File(new Storage\Disk(__DIR__.'/../_examples', 'test.txt'));
        $this->assertSame(trim($file->read()), 'test succeeded');
    }
}
