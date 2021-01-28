<?php

namespace ricwein\FileSystem\Tests\Storage;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\Helper\Stream;
use ricwein\FileSystem\Storage;

class StreamTest extends TestCase
{
    /**
     * @throws RuntimeException
     * @throws UnsupportedException
     */
    public function testAttributeDetection(): void
    {
        $storage = new Storage\Stream(Stream::fromResourceName('php://temp', 'rb+'));

        self::assertTrue($storage->isFile());
        self::assertFalse($storage->isDir());
        self::assertTrue($storage->isWriteable());
        self::assertTrue($storage->isReadable());
        self::assertFalse($storage->isDotfile());
        self::assertFalse($storage->isExecutable());
        self::assertFalse($storage->isSymlink());

        $storage = new Storage\Stream(Stream::fromResourceName('php://temp', 'rb'));
        self::assertSame('rb', $storage->getStream()->getAttribute('mode'));
        self::assertFalse($storage->isWriteable());
        self::assertTrue($storage->isReadable());

        $storage = new Storage\Stream(Stream::fromResourceName('php://temp', 'wb'));
        self::assertSame('wb', $storage->getStream()->getAttribute('mode'));
        self::assertTrue($storage->isWriteable());
        self::assertFalse($storage->isReadable());
    }

    /**
     * @throws RuntimeException
     * @throws UnsupportedException
     */
    public function testReadOfWriteOnlyFileAnomaly(): void
    {
        $storage = new Storage\Stream(fopen('php://temp', 'wb'));

        // The stream should be write-only but is also readable.
        // This test is in place to watch out for changes on the behavior.
        self::assertTrue($storage->isReadable());
        self::assertNotSame('wb', $storage->getStream()->getAttribute('mode'));
    }

    /**
     * @throws RuntimeException
     * @throws UnsupportedException
     * @throws FileNotFoundException
     * @throws UnexpectedValueException
     */
    public function testHashCalculation(): void
    {
        $origin = new Storage\Disk(__DIR__, '../', '_examples', 'archive.zip');
        $fileStream = new Storage\Stream(fopen($origin->path()->real, 'rb'));
        $tempStream = new Storage\Stream(fopen('php://temp', 'rb+'));
        $tempStream->writeFile($origin->readFile());

        self::assertSame($origin->readFile(), $fileStream->readFile());

        // Locking a temp-file is usually a bad idea and often results in 'unable to get file-lock' error
        self::assertSame($origin->readFile(), $tempStream->readFile(0, null, 0));

        self::assertSame($origin->getFileHash(), $fileStream->getFileHash());
        self::assertSame($origin->getFileHash(), $tempStream->getFileHash());
    }

}
