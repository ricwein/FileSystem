<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\File;

use Exception;
use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\FilesystemException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Helper\Stream;
use ricwein\FileSystem\Storage;

class StreamTest extends TestCase
{
    /**
     * @throws FilesystemException
     */
    public function testFileStream(): void
    {
        $file = new File(new Storage\Disk(__DIR__ . '/../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        self::assertSame(
            $file->read(),
            file_get_contents(__DIR__ . '/../_examples/test.txt')
        );

        ob_start();
        $file->stream();
        $content = ob_get_clean();

        self::assertSame(
            $file->read(),
            $content
        );
    }

    /**
     * @throws FilesystemException
     * @throws Exception
     */
    public function testMemoryStream(): void
    {
        $message = bin2hex(random_bytes(2 ** 10));
        $file = new File(new Storage\Memory($message));
        self::assertSame($file->read(), $message);

        ob_start();
        $file->stream();
        $content = ob_get_clean();

        self::assertSame($file->read(), $content);
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws Exception
     */
    public function testStreamCopy(): void
    {
        $message = bin2hex(random_bytes(2 ** 10));
        $file = new File(new Storage\Memory($message));
        self::assertSame($file->read(), $message);

        ob_start();
        $file->getStream('rb')->copyToStream(Stream::fromResourceName('php://output', 'wb'));
        $content = ob_get_clean();

        self::assertSame($file->read(), $content);
    }
}
