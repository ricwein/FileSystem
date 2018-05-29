<?php declare(strict_types = 1);

namespace ricwein\FileSystem\Tests\Extensions\Binary;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;

/**
 * test Temp-Storage
 *
 * @author Richard Weinhold
 */
class DiskTest extends TestCase
{
    /**
     * @return void
     */
    public function testWriteRead()
    {
        $message = random_bytes(2 ** 12);

        $file = new File(new Storage\Disk\Temp());
        $file->binary()->writeBytes($message);

        $this->assertSame($file->binary(true)->readBytes(2 ** 12), $message);
    }

    /**
     * @expectedException \ricwein\FileSystem\Exceptions\RuntimeException
     * @return void
     */
    public function testOOBRead()
    {
        $message = random_bytes(2 ** 12);

        $file = new File(new Storage\Disk\Temp());
        $writeFile = $file->binary();
        $writeFile->writeBytes($message);

        $this->assertSame($file->binary()->readBytes((2 ** 12) + 1), $message);
    }

    /**
     * @expectedException \ricwein\FileSystem\Exceptions\AccessDeniedException
     * @return void
     */
    public function testHandleLock()
    {
        $message = random_bytes(2 ** 12);

        $file = new File(new Storage\Disk\Temp());
        $writeFile = $file->binary();
        $writeFile->writeBytes($message);

        $writeFile->readBytes(2 ** 12);
    }
}
