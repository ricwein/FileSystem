<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\File;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\Exception;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use function bin2hex;
use function random_bytes;

class WriteTest extends TestCase
{
    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws \Exception
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
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws \Exception
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
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws \Exception
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
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws \Exception
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
