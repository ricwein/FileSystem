<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Helper;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\Exception;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\File;

use ricwein\FileSystem\Helper\Path;

use ricwein\FileSystem\Storage;

/**
 * @author Richard Weinhold
 */
class PathParserTest extends TestCase
{
    /**
     * @throws UnexpectedValueException
     */
    public function testPathParsing(): void
    {
        $pathA = new Path([__DIR__ . '/../_examples', 'test.txt']);
        $pathB = new Path([__DIR__, '/../', '_examples', 'test.txt']);

        self::assertSame($pathA->real, $pathB->real);
        self::assertSame($pathA->directory, $pathB->directory);
        self::assertSame($pathA->raw, $pathB->raw);

        self::assertSame($pathA->filename, $pathB->filename);
        self::assertSame($pathA->basename, $pathB->basename);
        self::assertSame($pathA->extension, $pathB->extension);

        self::assertNotSame($pathA->safepath, $pathB->safepath);
        self::assertNotSame($pathA->filepath, $pathB->filepath);
    }

    /**
     * @throws UnexpectedValueException
     * @throws RuntimeException
     */
    public function testPathSelfSimilar(): void
    {
        $path1 = new Path([__FILE__]);
        $path2 = new Path([$path1]);

        self::assertSame($path1->getDetails(), $path2->getDetails());
    }

    /**
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testMultiPathSelfSimilar(): void
    {
        $path1 = new Path([__DIR__, basename(__FILE__)]);
        $path2 = new Path([$path1]);

        self::assertSame($path1->getDetails(), $path2->getDetails());
    }

    /**
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws AccessDeniedException
     * @throws Exception
     */
    public function testFilePathReusing(): void
    {
        $file1 = new File(new Storage\Disk(__FILE__));
        $file2 = new File(new Storage\Disk($file1->path()));

        self::assertSame($file1->storage()->getDetails(), $file2->storage()->getDetails());
    }

    /**
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws AccessDeniedException
     * @throws Exception
     */
    public function testFileMultiPathReusing(): void
    {
        $file1 = new File(new Storage\Disk(__DIR__, basename(__FILE__)));
        $file2 = new File(new Storage\Disk($file1->path()));

        self::assertSame($file1->storage()->getDetails(), $file2->storage()->getDetails());
    }
}
