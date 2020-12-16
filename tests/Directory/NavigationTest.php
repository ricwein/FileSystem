<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Directory;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\Exception;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Helper\Constraint;
use ZipArchive;

class NavigationTest extends TestCase
{
    /**
     * @throws AccessDeniedException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testDirChange(): void
    {
        $dir = new Directory(new Storage\Disk(__DIR__ . '/../'));
        self::assertSame($dir->path()->real, realpath(__DIR__ . '/../'));

        $dir->cd('_examples');
        self::assertSame($dir->path()->real, realpath(__DIR__ . '/../_examples/'));
    }

    /**
     * @throws AccessDeniedException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testDirUp(): void
    {
        $dir = new Directory(new Storage\Disk(__DIR__));
        self::assertSame($dir->path()->real, realpath(__DIR__));

        $dir->up(2);
        self::assertSame($dir->path()->real, realpath(__DIR__ . '/../../'));
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws Exception
     * @throws FileNotFoundException
     */
    public function testFileRead(): void
    {
        $dir = new Directory(new Storage\Disk(__DIR__), Constraint::LOOSE);

        self::assertSame(
            $dir->up(2)->file('LICENSE')->read(),
            file_get_contents(__DIR__ . '/../../LICENSE')
        );

        $exampleDir = new Directory(new Storage\Disk(__DIR__, '..', '_examples'), Constraint::LOOSE);
        $zipFile = $exampleDir->file('archive.zip', Constraint::LOOSE, File\Zip::class, ZipArchive::CREATE);

        self::assertTrue($zipFile->isFile());
        self::assertSame(File\Zip::class, get_class($zipFile));
        self::assertTrue(method_exists($zipFile, 'extractTo'));
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws Exception
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testDirUpError(): void
    {
        $dir = new Directory(new Storage\Disk(__DIR__));
        self::assertSame($dir->path()->real, realpath(__DIR__));

        $this->expectException(ConstraintsException::class);
        $this->expectExceptionMessageMatches('/.*constraint failed: the given real-path.*is not within the safepath.*/');

        $dir->up(2);
        $dir->file('LICENSE')->read();
    }
}
