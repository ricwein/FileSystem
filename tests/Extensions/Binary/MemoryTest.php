<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Extensions\Binary;

use Exception;
use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\FilesystemException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Storage\Extensions\Binary;

class MemoryTest extends TestCase
{
    protected const MSG_LENGTH = 2 ** 12;

    /**
     * @throws FilesystemException
     * @throws Exception
     */
    public function testWriteRead(): void
    {
        $message = random_bytes(self::MSG_LENGTH);

        $file = new File(new Storage\Memory());
        $file->getHandle(Binary::MODE_WRITE)->write($message);

        self::assertSame($file->getHandle(Binary::MODE_READ)->read(self::MSG_LENGTH), $message);
    }

    /**
     * @throws FilesystemException
     * @throws Exception
     */
    public function testOOBRead(): void
    {
        $message = random_bytes(self::MSG_LENGTH);

        $file = new File(new Storage\Memory());
        $file->getHandle(Binary::MODE_WRITE)->write($message);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Out-of-bounds read");

        $file->getHandle(Binary::MODE_READ)->read(self::MSG_LENGTH + 1);
    }

    /**
     * @throws FilesystemException
     * @throws Exception
     */
    public function testHandleLock(): void
    {
        $message = random_bytes(self::MSG_LENGTH);

        $file = new File(new Storage\Memory());
        $byteHandle = $file->getHandle(Binary::MODE_WRITE);
        self::assertSame(self::MSG_LENGTH, $byteHandle->write($message));
        self::assertSame($file->getHandle(Binary::MODE_READ)->read(self::MSG_LENGTH), $message);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage("unable to switch access-mode for existing binary file handle");

        $byteHandle->read(self::MSG_LENGTH);
    }
}
