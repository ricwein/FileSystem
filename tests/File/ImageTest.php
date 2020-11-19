<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\File;

use \League\Flysystem\FileExistsException as FlyFileExistsException;
use \League\Flysystem\FileNotFoundException as FlyFileNotFoundException;
use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\Exception;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use Intervention\Image\Image as IImage;
use ricwein\FileSystem\Enum\Hash;
use ricwein\FileSystem\Helper\MimeType;
use ricwein\FileSystem\Helper\Constraint;

class ImageTest extends TestCase
{

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws FlyFileExistsException
     * @throws FlyFileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function testImageDetection(): void
    {
        $image = new File\Image(new Storage\Disk(__DIR__, '../_examples', 'test.png'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        self::assertTrue(MimeType::isImage($image->getType()));

        $image = $image->copyTo(new Storage\Disk\Temp)->encode('jpg');
        self::assertTrue(MimeType::isImage($image->getType()));
        self::assertSame(MimeType::getMimeFor('jpg'), $image->getType());

        $image = new File\Image(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        self::assertFalse(MimeType::isImage($image->getType()));

        $image = new File\Image(new Storage\Memory(file_get_contents(__DIR__ . '/../_examples/test.png')));
        self::assertTrue(MimeType::isImage($image->getType()));

        $image = $image->copyTo(new Storage\Memory)->encode('jpg');
        self::assertTrue(MimeType::isImage($image->getType()));
        self::assertSame(MimeType::getMimeFor('jpg'), $image->getType());

        $image = new File\Image(new Storage\Memory(file_get_contents(__DIR__ . '/../_examples/test.txt')));
        self::assertFalse(MimeType::isImage($image->getType()));
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws FlyFileExistsException
     * @throws FlyFileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function testReencoding(): void
    {
        $source = new File\Image(new Storage\Disk(__DIR__, '../_examples', 'test.png'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $source = $source->copyTo(new Storage\Disk\Temp)->encode('jpg');
        $sourceHash = $source->getHash(Hash::CONTENT);

        $diskImage = $source->copyTo(new Storage\Disk\Temp);
        $memoryImage = $source->copyTo(new Storage\Memory);

        self::assertSame($sourceHash, $diskImage->getHash(Hash::CONTENT));
        self::assertSame($sourceHash, $memoryImage->getHash(Hash::CONTENT));

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
        self::assertSame($diskImage->getHash(Hash::CONTENT), $memoryImage->getHash(Hash::CONTENT));

        $diskImage->encode('jpg');
        $memoryImage->encode('jpg');

        self::assertNotSame($sourceHash, $diskImage->getHash(Hash::CONTENT));
        self::assertNotSame($sourceHash, $memoryImage->getHash(Hash::CONTENT));
        self::assertSame($diskImage->getHash(Hash::CONTENT), $memoryImage->getHash(Hash::CONTENT));
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws FlyFileExistsException
     * @throws FlyFileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function testCompression(): void
    {
        $source = new File\Image(new Storage\Disk(__DIR__, '../_examples', 'test.png'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $source = $source->copyTo(new Storage\Disk\Temp)->encode('jpg');

        $shouldSize = (int)floor($source->getSize() / 1.1);
        self::assertGreaterThanOrEqual($shouldSize, $source->getSize());

        $image = $source->copyTo(new Storage\Disk\Temp);
        $image->compress($shouldSize);

        self::assertGreaterThanOrEqual($image->getSize(), $shouldSize);

        $image = $source->copyTo(new Storage\Memory);
        $image->compress($shouldSize);

        self::assertGreaterThanOrEqual($image->getSize(), $shouldSize);
        self::assertSame($image->getType(), MimeType::getMimeFor('jpg'));
    }
}
