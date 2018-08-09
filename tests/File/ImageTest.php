<?php declare(strict_types = 1);

namespace ricwein\FileSystem\Tests\File;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use Intervention\Image\Image as IImage;
use ricwein\FileSystem\Helper\Hash;
use ricwein\FileSystem\Helper\MimeType;
use ricwein\FileSystem\Helper\Constraint;

/**
 * test FileSyst\File bases
 *
 * @author Richard Weinhold
 */
class ImageTest extends TestCase
{

    /**
     * @return void
     */
    public function testImageDetection()
    {
        $image = new File\Image(new Storage\Disk(__DIR__, '../_examples', 'test.png'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $this->assertTrue(MimeType::isImage($image->getType()));

        $image = $image->copyTo(new Storage\Disk\Temp)->encode('jpg');
        $this->assertTrue(MimeType::isImage($image->getType()));
        $this->assertSame(MimeType::getMimeFor('jpg'), $image->getType());

        $image = new File\Image(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $this->assertFalse(MimeType::isImage($image->getType()));

        $image = new File\Image(new Storage\Memory(file_get_contents(__DIR__.'/../_examples/test.png')));
        $this->assertTrue(MimeType::isImage($image->getType()));

        $image = $image->copyTo(new Storage\Memory)->encode('jpg');
        $this->assertTrue(MimeType::isImage($image->getType()));
        $this->assertSame(MimeType::getMimeFor('jpg'), $image->getType());

        $image = new File\Image(new Storage\Memory(file_get_contents(__DIR__.'/../_examples/test.txt')));
        $this->assertFalse(MimeType::isImage($image->getType()));
    }

    /**
     * @return void
     */
    public function testReencoding()
    {
        $source = new File\Image(new Storage\Disk(__DIR__, '../_examples', 'test.png'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $source = $source->copyTo(new Storage\Disk\Temp)->encode('jpg');
        $sourceHash = $source->getHash(Hash::CONTENT);

        $diskImage = $source->copyTo(new Storage\Disk\Temp);
        $memoryImage = $source->copyTo(new Storage\Memory);

        $this->assertSame($sourceHash, $diskImage->getHash(Hash::CONTENT));
        $this->assertSame($sourceHash, $memoryImage->getHash(Hash::CONTENT));

        $diskHash = null;
        $memoryHash = null;

        $diskImage->edit(function (IImage $image) use ($diskHash): IImage {
            $diskHash = hash('sha256', (string) $image);
            return $image;
        });
        $memoryImage->edit(function (IImage $image) use ($memoryHash): IImage {
            $memoryHash = hash('sha256', (string) $image);
            return $image;
        });

        $this->assertSame($diskHash, $memoryHash);
        $this->assertSame($diskImage->getHash(Hash::CONTENT), $memoryImage->getHash(Hash::CONTENT));

        $diskImage->encode('jpg');
        $memoryImage->encode('jpg');

        $this->assertNotSame($sourceHash, $diskImage->getHash(Hash::CONTENT));
        $this->assertNotSame($sourceHash, $memoryImage->getHash(Hash::CONTENT));
        $this->assertSame($diskImage->getHash(Hash::CONTENT), $memoryImage->getHash(Hash::CONTENT));
    }

    /**
     * @return void
     */
    public function testCompression()
    {
        $source = new File\Image(new Storage\Disk(__DIR__, '../_examples', 'test.png'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $source = $source->copyTo(new Storage\Disk\Temp)->encode('jpg');

        $shouldSize = (int) floor($source->getSize() / 1.1);
        $this->assertGreaterThanOrEqual($shouldSize, $source->getSize());

        /** @var File\Image $image */
        $image = $source->copyTo(new Storage\Disk\Temp);
        $image->compress($shouldSize);

        $this->assertGreaterThanOrEqual($image->getSize(), $shouldSize);

        /** @var File\Image $image */
        $image = $source->copyTo(new Storage\Memory);
        $image->compress($shouldSize);

        $this->assertGreaterThanOrEqual($image->getSize(), $shouldSize);
        $this->assertSame($image->getType(), MimeType::getMimeFor('jpg'));
    }
}
