<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\File;

use Intervention\Image\Image as IImage;
use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\FilesystemException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Helper\MimeType;
use ricwein\FileSystem\Storage;

class ImageTest extends TestCase
{

    /**
     * @throws FilesystemException
     */
    public function testImageDetection(): void
    {
        $image = new File\Image(new Storage\Disk(__DIR__, '../_examples', 'test.png'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        self::assertTrue(MimeType::isImage($image->getType()));

        $image = $image->copyTo(new Storage\Disk\Temp())->encode('jpg');
        self::assertTrue(MimeType::isImage($image->getType()));
        self::assertSame(MimeType::getMimeFor('jpg'), $image->getType());

        $image = new File\Image(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        self::assertFalse(MimeType::isImage($image->getType()));

        $image = new File\Image(new Storage\Memory(file_get_contents(__DIR__ . '/../_examples/test.png')));
        self::assertTrue(MimeType::isImage($image->getType()));

        $image = $image->copyTo(new Storage\Memory())->encode('jpg');
        self::assertTrue(MimeType::isImage($image->getType()));
        self::assertSame(MimeType::getMimeFor('jpg'), $image->getType());

        $image = new File\Image(new Storage\Memory(file_get_contents(__DIR__ . '/../_examples/test.txt')));
        self::assertFalse(MimeType::isImage($image->getType()));
    }

    /**
     * @throws FilesystemException
     */
    public function testReEncoding(): void
    {
        $source = new File\Image(new Storage\Disk(__DIR__, '../_examples', 'test.png'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $source = $source->copyTo(new Storage\Disk\Temp())->encode('jpg');
        $sourceHash = $source->getHash();

        $diskImage = $source->copyTo(new Storage\Disk\Temp());
        $memoryImage = $source->copyTo(new Storage\Memory());

        self::assertSame($sourceHash, $diskImage->getHash());
        self::assertSame($sourceHash, $memoryImage->getHash());

        $diskHash = null;
        $memoryHash = null;

        $diskImage->edit(function (IImage $image) use (&$diskHash): IImage {
            $diskHash = hash('sha256', (string)$image);
            return $image;
        });
        $memoryImage->edit(function (IImage $image) use (&$memoryHash): IImage {
            $memoryHash = hash('sha256', (string)$image);
            return $image;
        });

        self::assertSame($diskHash, $memoryHash);
        self::assertSame($diskImage->getHash(), $memoryImage->getHash());

        $diskImage->encode('jpg');
        $memoryImage->encode('jpg');

        self::assertNotSame($sourceHash, $diskImage->getHash());
        self::assertNotSame($sourceHash, $memoryImage->getHash());
        self::assertSame($diskImage->getHash(), $memoryImage->getHash());
    }

    /**
     * @throws FilesystemException
     */
    public function testCompression(): void
    {
        $source = new File\Image(new Storage\Disk(__DIR__, '../_examples', 'test.png'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $source = $source->copyTo(new Storage\Disk\Temp())->encode('jpg');

        $shouldSize = (int)floor($source->getSize()->getBytes() / 1.1);
        self::assertGreaterThanOrEqual($shouldSize, $source->getSize()->getBytes());

        $image = $source->copyTo(new Storage\Disk\Temp());
        $image->compress($shouldSize);

        self::assertGreaterThanOrEqual($image->getSize()->getBytes(), $shouldSize);

        $image = $source->copyTo(new Storage\Memory());
        $image->compress($shouldSize);

        self::assertGreaterThanOrEqual($image->getSize()->getBytes(), $shouldSize);
        self::assertSame($image->getType(), MimeType::getMimeFor('jpg'));
    }
}
