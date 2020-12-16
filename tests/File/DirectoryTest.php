<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\File;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\Exception;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;

class DirectoryTest extends TestCase
{
    /**
     * @throws AccessDeniedException
     * @throws Exception
     * @throws RuntimeException
     */
    public function testSinglePath(): void
    {
        $file = new File(new Storage\Disk(__FILE__));
        $dir = $file->dir();

        self::assertTrue($file->isValid());
        self::assertTrue($dir->isValid());

        self::assertSame($file->path()->directory, $dir->path()->real);
        self::assertSame(__DIR__, $dir->path()->real);
        self::assertSame(dirname($file->path()->real), $dir->path()->real);
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testTwoPartedPath(): void
    {
        $sDir = realpath(__DIR__ . '/../../');
        $sFile = str_replace($sDir, '', __FILE__);

        $file = new File(new Storage\Disk(__DIR__ . '/../../', $sFile));

        $dir = $file->dir();

        self::assertTrue($file->isValid());
        self::assertTrue($dir->isValid());

        self::assertSame(realpath($file->path()->directory), $dir->path()->real);
        self::assertSame(__DIR__, $dir->path()->real);
        self::assertSame(dirname($file->path()->real), $dir->path()->real);
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testThreePartedPath(): void
    {
        $sDir1 = realpath(__DIR__ . '/../');
        $sDir2 = str_replace($sDir1, '', __DIR__);
        $sFile = basename(__FILE__);

        $file = new File(new Storage\Disk($sDir1, $sDir2, $sFile));

        $dir = $file->dir();

        self::assertTrue($file->isValid());
        self::assertTrue($dir->isValid());

        self::assertSame(realpath($file->path()->directory), $dir->path()->real);
        self::assertSame(__DIR__, $dir->path()->real);
        self::assertSame(dirname($file->path()->real), $dir->path()->real);
    }
}
