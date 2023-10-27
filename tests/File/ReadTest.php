<?php
declare(strict_types=1);

namespace ricwein\FileSystem\Tests\File;

use Exception;
use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\FilesystemException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Storage;

class ReadTest extends TestCase
{
    /**
     * @throws FilesystemException
     * @throws Exception
     */
    public function testFileRead(): void
    {
        $file = new File(new Storage\Disk(__DIR__ . '/../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        self::assertSame(
            $file->read(),
            file_get_contents(__DIR__ . '/../_examples/test.txt')
        );

        $message = bin2hex(random_bytes(2 ** 10));
        $file = new File(new Storage\Memory($message));
        self::assertSame(
            $file->read(),
            $message
        );
    }

    /**
     * @throws FilesystemException
     * @throws Exception
     */
    public function testLineRead(): void
    {
        $file = new File(new Storage\Disk(__DIR__ . '/../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        self::assertSame(
            $file->readAsLines(),
            file(__DIR__ . '/../_examples/test.txt')
        );

        $message = bin2hex(random_bytes(2 ** 10));
        $message = chunk_split($message, 8, PHP_EOL);

        $file = new File(new Storage\Memory($message));
        self::assertSame(
            $file->readAsLines(),
            explode(PHP_EOL, $message)
        );
    }

    /**
     * @throws FilesystemException
     * @throws Exception
     */
    public function testPartialFileRead(): void
    {
        $file = new File(new Storage\Disk(__DIR__ . '/../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);

        $length = (int)floor($file->getSize() / 2);
        self::assertNotEmpty($length);
        self::assertSame(
            $file->read(0, $length),
            mb_substr(file_get_contents(__DIR__ . '/../_examples/test.txt'), 0, $length)
        );

        $message = bin2hex(random_bytes(2 ** 10));
        $file = new File(new Storage\Memory($message));

        $length = (int)floor($file->getSize() / 2);
        self::assertNotEmpty($length);
        self::assertSame(
            $file->read(0, $length),
            mb_substr($message, 0, $length)
        );

        $offset = $length;
        self::assertSame(
            $file->read($offset),
            mb_substr($message, $offset)
        );

        $length = (int)floor($file->getSize() / 3);
        $offset = $length;
        self::assertSame(
            $file->read($offset, $length),
            mb_substr($message, $offset, $length)
        );
    }

    /**
     * @throws FilesystemException
     * @throws Exception
     */
    public function testFileMimeTypeGuessing(): void
    {
        $file = new File(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        self::assertSame($file->getType(), 'text/plain');

        $file = new File(new Storage\Disk(__DIR__, '../_examples', 'test.json'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        self::assertSame($file->getType(), 'application/json');
    }
}
