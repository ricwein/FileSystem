<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Helper;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Path;

/**
 * @author Richard Weinhold
 */
class PathParserTest extends TestCase
{
    public function testPathParsing(): void
    {
        $pathA = new Path(__DIR__ . '/../_examples/test.txt');
        $pathB = new Path(__DIR__ . '/../_examples', 'test.txt');
        $pathC = new Path(__DIR__, '/../', '_examples', 'test.txt');

        self::assertSame(realpath(__DIR__ . '/../_examples/test.txt'), $pathA->getRealPath());
        self::assertSame(realpath(__DIR__ . '/../_examples/test.txt'), $pathB->getRealPath());
        self::assertSame(realpath(__DIR__ . '/../_examples/test.txt'), $pathC->getRealPath());

        self::assertSame(realpath(__DIR__ . '/../_examples/'), $pathA->getDirectory());
        self::assertSame(realpath(__DIR__ . '/../_examples/'), $pathB->getDirectory());
        self::assertSame(realpath(__DIR__ . '/../_examples/'), $pathC->getDirectory());

        self::assertSame($pathA->getRawPath(), $pathB->getRawPath());
        self::assertSame($pathA->getRawPath(), $pathC->getRawPath());

        self::assertSame('test.txt', $pathA->getFilename());
        self::assertSame('test.txt', $pathB->getFilename());
        self::assertSame('test.txt', $pathC->getFilename());

        self::assertSame('test', $pathA->getBasename());
        self::assertSame('test', $pathB->getBasename());
        self::assertSame('test', $pathC->getBasename());

        self::assertSame('txt', $pathA->getExtension());
        self::assertSame('txt', $pathB->getExtension());
        self::assertSame('txt', $pathC->getExtension());

        self::assertSame(realpath(__DIR__ . '/../_examples/'), realpath($pathA->getSafePath()));
        self::assertSame(__DIR__ . '/../_examples', $pathA->getSafePath());
        self::assertSame(realpath(__DIR__ . '/../_examples/'), realpath($pathB->getSafePath()));
        self::assertSame(realpath(__DIR__), $pathC->getSafePath());

        self::assertSame(__DIR__, $pathC->getSafePath());
    }

    public function testPathSelfSimilar(): void
    {
        $path1 = new Path(__FILE__);
        $path2 = new Path($path1);

        self::assertSame(print_r($path1, true), print_r($path2, true));
    }

    public function testMultiPathSelfSimilar(): void
    {
        $path1 = new Path(__DIR__, basename(__FILE__));
        $path2 = new Path($path1);

        self::assertSame(print_r($path1, true), print_r($path2, true));
    }

    public function testSafePathParsing(): void
    {
        $path1 = new Path(__DIR__, '..', '_examples');
        $path2 = new Path($path1, 'archive.zip');

        self::assertTrue($path1->doesExist());
        self::assertTrue($path1->isDir());

        self::assertSame(__DIR__, $path1->getSafePath());
        self::assertTrue($path1->isInSafePath(__DIR__));
        self::assertFalse($path1->isInSafePath());

        self::assertTrue($path2->doesExist());
        self::assertTrue($path2->isFile());
        self::assertSame($path1->getSafePath(), $path2->getSafePath());

        self::assertTrue($path2->isInSafePath(__DIR__));
        self::assertFalse($path2->isInSafePath());
    }

    public function testRelativePathResolver(): void
    {
        $path = new Path('/index/', '/public/', 'index.php');
        self::assertSame('public/index.php', $path->getRelativePath('/index/'));

        $path = new Path('/', 'index', 'public', 'index.php');
        self::assertSame('public/index.php', $path->getRelativePath('/index/'));

        $path = new Path('/index/');
        self::assertSame('', $path->getRelativePath('/index/'));

        $rootPath = new Path('/index');
        $path = new Path($rootPath, '/public/', 'index.php');
        self::assertSame('public/index.php', $path->getRelativePath($rootPath));
    }
}
