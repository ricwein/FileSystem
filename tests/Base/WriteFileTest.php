<?php declare(strict_types = 1);

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;

/**
 * test FileSyst\File bases
 *
 * @author Richard Weinhold
 */
class WriteFileTest extends TestCase
{
    /**
     * @return void
     */
    public function testFileWriteTempDisk()
    {
        $message = \bin2hex(\random_bytes(16384));

        $file = new File(new Storage\Disk\Temp());
        $file->write($message);

        $this->assertSame($message, $file->read());
    }

    /**
     * @return void
     */
    public function testFileWriteMemory()
    {
        $message = \bin2hex(\random_bytes(16384));

        $file = new File(new Storage\Memory());
        $file->write($message);

        $this->assertSame($message, $file->read());
    }
}
