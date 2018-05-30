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
     * @var int
     */
    protected const MSG_LENGTH = 2 ** 12;

    /**
     * @return void
     */
    public function testWriteRead()
    {
        $message = random_bytes(self::MSG_LENGTH);

        $file = new File(new Storage\Disk\Temp());
        $file->binary()->write($message);

        $this->assertSame($file->binary(true)->read(self::MSG_LENGTH), $message);
    }

    /**
     * @expectedException \ricwein\FileSystem\Exceptions\RuntimeException
     * @return void
     */
    public function testOOBRead()
    {
        $message = random_bytes(self::MSG_LENGTH);

        $file = new File(new Storage\Disk\Temp());
        $byteHandle = $file->binary();
        $byteHandle->write($message);

        $this->assertSame($file->binary()->read((self::MSG_LENGTH) + 1), $message);
    }

    /**
     * @expectedException \ricwein\FileSystem\Exceptions\AccessDeniedException
     * @return void
     */
    public function testHandleLock()
    {
        $message = random_bytes(self::MSG_LENGTH);

        $file = new File(new Storage\Disk\Temp());
        $byteHandle = $file->binary();

        // write bytes and validate if correct length was written
        $this->assertSame(self::MSG_LENGTH, $byteHandle->write($message, self::MSG_LENGTH));

        // try to read from write-locked handle => this must fail
        $byteHandle->read(self::MSG_LENGTH);
    }
}
