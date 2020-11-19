<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Helper;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\Exception;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Helper\Path;
use ricwein\FileSystem\Helper\PathFinder;

/**
 * @author Richard Weinhold
 */
class PathFinderTest extends TestCase
{
    /**
     * @throws FileNotFoundException
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testStrings(): void
    {
        $current = __FILE__;
        $file = new File(PathFinder::try([
            "{$current}.notExistingFilename",
            $current,
        ]));

        self::assertTrue($file->isFile());
        self::assertSame($file->path()->real, $current);
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testPaths(): void
    {
        $file = new File(PathFinder::try([
            new Path([__FILE__, '.notExistingFilename']),
            new Path([__FILE__]),
        ]));

        self::assertTrue($file->isFile());
        self::assertSame($file->path()->real, __FILE__);
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testStorages(): void
    {
        $file = new File(PathFinder::try([
            new Storage\Disk(__FILE__, '.notExistingFilename'),
            new Storage\Disk(__FILE__),
        ]));

        self::assertTrue($file->isFile());
        self::assertSame($file->path()->real, __FILE__);
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testTempPaths(): void
    {
        $file = new File(PathFinder::try([
            new File(new Storage\Disk(__FILE__, '.notExistingFilename')),
            new File(new Storage\Disk\Temp()),
        ]));

        self::assertTrue($file->isFile());
        self::assertInstanceOf(Storage\Disk\Temp::class, $file->storage());
        self::assertSame($file->directory()->path()->real, realpath(sys_get_temp_dir()));
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testFiles(): void
    {
        $file = new File(PathFinder::try([
            new File(new Storage\Disk(__FILE__, '.notExistingFilename')),
            new File(new Storage\Disk(__FILE__)),
        ]));

        self::assertTrue($file->isFile());
        self::assertSame($file->path()->real, realpath(__FILE__));
    }

    /**
     * @throws AccessDeniedException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testErrors(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage("file not found");

        new File(PathFinder::try([
            new Path([__FILE__, '.notExistingFilename']),
            new File(new Storage\Disk(__FILE__, '.notExistingFilename')),
            new File(new Storage\Disk(__DIR__, '.notExistingFilename')),
        ]));
    }
}
