<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Storage;

use League\Flysystem\FilesystemException as FlysystemFilesystemException;
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
use ricwein\FileSystem\Helper\MimeType;
use ricwein\FileSystem\Helper\Constraint;

/**
 * test Temp-Storage
 *
 * @author Richard Weinhold
 */
class MemoryResourceTest extends TestCase
{
    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     * @throws FlysystemFilesystemException
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
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function testResourceGD(): void
    {
        $resource = imagecreatetruecolor(1024, 1024);
        $memory = new File(new Storage\Memory\Resource($resource));

        self::assertNotEmpty($memory->read());
        self::assertSame($memory->getType(), MimeType::getMimeFor('png'));
    }
}
