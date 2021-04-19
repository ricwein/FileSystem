<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Directory;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\Exception;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\Storage;

class CopyTest extends TestCase
{
    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     * @throws Exception
     */
    public function testDirectoryCopy(): void
    {
        $source = new Directory(new Storage\Disk(__DIR__ . '/..'));
        $destination = new Directory(new Storage\Disk\Temp());

        $source->copyTo($destination->storage());

        self::assertSame($source->getSize(), $destination->getSize());
        self::assertSame($source->getHash(), $destination->getHash());

        self::assertDirectoryExists($destination->path()->real . '/Directory');
        self::assertDirectoryExists($destination->path()->real . '/_examples');

        self::assertFileExists($destination->path()->real . '/_examples/archive.zip');
        self::assertFileExists($destination->path()->real . '/Directory/CopyTest.php');
    }
}
