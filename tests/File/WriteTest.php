<?php declare(strict_types = 1);

namespace ricwein\FileSystem\Tests\File;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;

/**
 * test FileSyst\File bases
 *
 * @author Richard Weinhold
 */
class WriteTest extends TestCase
{
    /**
     * @return void
     */
    public function testFileWriteTempDisk()
    {
        $message = \bin2hex(\random_bytes(2 ** 10));

        $file = new File(new Storage\Disk\Temp());
        $file->write($message);

        $this->assertSame(
            $message,
            $file->read()
        );
    }

    /**
     * @return void
     */
    public function testFileWriteMemory()
    {
        $message = \bin2hex(\random_bytes(2 ** 10));

        $file = new File(new Storage\Memory());
        $file->write($message);

        $this->assertSame($message, $file->read());
    }
}
