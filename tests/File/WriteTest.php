<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\File;

use Exception;
use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\FilesystemException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;

class WriteTest extends TestCase
{
    /**
     * @throws FilesystemException
     * @throws Exception
     */
    public function testFileOverwriteTempDisk(): void
    {
        $file = new File(new Storage\Disk\Temp());

        $message = bin2hex(random_bytes(2 ** 10));
        $file->write($message);

        // overwrite file-content
        $message = bin2hex(random_bytes(2 ** 9));
        $file->write($message);

        self::assertSame($message, $file->read());
    }

    /**
     * @throws FilesystemException
     * @throws Exception
     */
    public function testFileWriteAppendTempDisk(): void
    {
        $file = new File(new Storage\Disk\Temp());

        $messageA = bin2hex(random_bytes(2 ** 10));
        $file->write($messageA);

        // overwrite file-content
        $messageB = bin2hex(random_bytes(2 ** 9));
        $file->write($messageB, true);

        self::assertSame($messageA . $messageB, $file->read());
    }

    /**
     * @throws FilesystemException
     * @throws Exception
     */
    public function testFileOverwriteMemory(): void
    {
        $file = new File(new Storage\Memory());

        $message = bin2hex(random_bytes(2 ** 10));
        $file->write($message);

        // overwrite file-content
        $message = bin2hex(random_bytes(2 ** 9));
        $file->write($message);

        self::assertSame($message, $file->read());
    }

    /**
     * @throws FilesystemException
     * @throws Exception
     */
    public function testFileWriteAppendMemory(): void
    {
        $file = new File(new Storage\Memory());

        $messageA = bin2hex(random_bytes(2 ** 10));
        $file->write($messageA);

        // overwrite file-content
        $messageB = bin2hex(random_bytes(2 ** 9));
        $file->write($messageB, true);

        self::assertSame($messageA . $messageB, $file->read());
    }
}
