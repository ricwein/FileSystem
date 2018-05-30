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
class MemoryTest extends TestCase
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

        $file = new File(new Storage\Memory());
        $file->binary()->write($message);

        $this->assertSame($file->binary()->read(self::MSG_LENGTH), $message);
    }

    /**
     * @expectedException \ricwein\FileSystem\Exceptions\RuntimeException
     * @return void
     */
    public function testOOBRead()
    {
        $message = random_bytes(self::MSG_LENGTH);

        $file = new File(new Storage\Memory());
        $file->binary()->write($message);

        $this->assertSame($file->binary()->read((self::MSG_LENGTH) + 1), $message);
    }


    /**
     * @expectedException \ricwein\FileSystem\Exceptions\AccessDeniedException
     * @return void
     */
    public function testHandleLock()
    {
        $message = random_bytes(self::MSG_LENGTH);

        $file = new File(new Storage\Memory());
        $byteHandle = $file->binary();
        $this->assertSame(self::MSG_LENGTH, $byteHandle->write($message));
        $this->assertSame($file->binary(true)->read(self::MSG_LENGTH), $message);

        $byteHandle->read(self::MSG_LENGTH);
    }
}
