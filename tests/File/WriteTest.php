<?php

declare(strict_types=1);

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
    public function testFileOverwriteTempDisk()
    {
        $file = new File(new Storage\Disk\Temp());

        $message = \bin2hex(\random_bytes(2 ** 10));
        $file->write($message);

        // overwrite file-content
        $message = \bin2hex(\random_bytes(2 ** 9));
        $file->write($message);

        $this->assertSame($message, $file->read());
    }

    /**
     * @return void
     */
    public function testFileWriteAppendTempDisk()
    {
        $file = new File(new Storage\Disk\Temp());

        $messageA = \bin2hex(\random_bytes(2 ** 10));
        $file->write($messageA);

        // overwrite file-content
        $messageB = \bin2hex(\random_bytes(2 ** 9));
        $file->write($messageB, true);

        $this->assertSame($messageA . $messageB, $file->read());
    }

    /**
     * @return void
     */
    public function testFileOverwriteMemory()
    {
        $file = new File(new Storage\Memory());

        $message = \bin2hex(\random_bytes(2 ** 10));
        $file->write($message);

        // overwrite file-content
        $message = \bin2hex(\random_bytes(2 ** 9));
        $file->write($message);

        $this->assertSame($message, $file->read());
    }

    /**
     * @return void
     */
    public function testFileWriteAppendMemory()
    {
        $file = new File(new Storage\Memory());

        $messageA = \bin2hex(\random_bytes(2 ** 10));
        $file->write($messageA);

        // overwrite file-content
        $messageB = \bin2hex(\random_bytes(2 ** 9));
        $file->write($messageB, true);

        $this->assertSame($messageA . $messageB, $file->read());
    }
}
