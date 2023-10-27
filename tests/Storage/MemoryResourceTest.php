<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Storage;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\FilesystemException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Helper\MimeType;
use ricwein\FileSystem\Storage;

/**
 * test Temp-Storage
 *
 * @author Richard Weinhold
 */
class MemoryResourceTest extends TestCase
{
    /**
     * @throws FilesystemException
     */
    public function testResourceRead(): void
    {
        $file = new File(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);

        $resource = fopen($file->getPath()->getRealPath(), 'rb');
        $memory = new File(new Storage\Memory\Resource($resource));
        fclose($resource);
        self::assertSame($file->read(), $memory->read());

        $dest = $memory->copyTo(new Storage\Disk\Temp());
        self::assertSame($dest->read(), $memory->read());
    }

    /**
     * @throws FilesystemException
     */
    public function testResourceGD(): void
    {
        $resource = imagecreatetruecolor(1024, 1024);
        $memory = new File(new Storage\Memory\Resource($resource));

        self::assertNotEmpty($memory->read());
        self::assertSame($memory->getType(), MimeType::getMimeFor('png'));
    }
}
