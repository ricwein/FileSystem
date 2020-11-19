<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Extensions\Binary;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\Exception;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Storage\Extensions\Binary;

class DiskTest extends TestCase
{
    protected const MSG_LENGTH = 2 ** 12;

    /**
     * @throws AccessDeniedException
     * @throws RuntimeException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws UnsupportedException
     * @throws \Exception
     */
    public function testWriteRead(): void
    {
        $message = random_bytes(self::MSG_LENGTH);

        $file = new File(new Storage\Disk\Temp());
        $file->getHandle(Binary::MODE_WRITE)->write($message);

        self::assertSame($file->getHandle(Binary::MODE_READ)->read(self::MSG_LENGTH), $message);
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnsupportedException
     * @throws \Exception
     */
    public function testOOBRead(): void
    {
        $message = random_bytes(self::MSG_LENGTH);

        $file = new File(new Storage\Disk\Temp());
        $byteHandle = $file->getHandle(Binary::MODE_WRITE);
        $byteHandle->write($message);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("unable to get file-lock");

        $file->getHandle(Binary::MODE_READ)->read(self::MSG_LENGTH + 1);
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnsupportedException
     * @throws \Exception
     */
    public function testHandleLock(): void
    {
        $message = random_bytes(self::MSG_LENGTH);

        $file = new File(new Storage\Disk\Temp());
        $byteHandle = $file->getHandle(Binary::MODE_WRITE);

        // write bytes and validate if correct length was written
        self::assertSame(self::MSG_LENGTH, $byteHandle->write($message, self::MSG_LENGTH));

        // try to read from write-locked handle => this must fail
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage("unable to switch access-mode for existing binary file handle");

        $byteHandle->read(self::MSG_LENGTH);
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws UnsupportedException
     */
    public function testEmptyFile(): void
    {
        $file = new File(new Storage\Disk\Temp());
        $handle = $file->getHandle(Binary::MODE_READ);

        self::assertSame(0, $handle->getSize());
        self::assertSame(0, $handle->getPos());
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws UnsupportedException
     * @throws \Exception
     */
    public function testSeek(): void
    {
        $file = (new File(new Storage\Disk\Temp()))->write(random_bytes(self::MSG_LENGTH));
        $handle = $file->getHandle(Binary::MODE_READ);

        self::assertSame($file->getSize(), $handle->getSize());
        self::assertSame(0, $handle->getPos());

        $pos = (int)floor($file->getSize() / 2);
        $handle->seek($pos);
        self::assertSame($pos, $handle->getPos());
        self::assertSame($file->getSize() - $pos, $handle->remainingBytes());
    }
}
