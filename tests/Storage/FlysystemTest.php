<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\Storage;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Helper\Constraint;

use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Directory;
use League\Flysystem\Adapter\Local;

/**
 * test FlySystem-Storage Adapter
 *
 * @author Richard Weinhold
 * @requires
 */
class FlysystemTest extends TestCase
{
    public function setUp()
    {
        if (!class_exists('League\Flysystem\Filesystem')) {
            $this->markTestSkipped('The required package "League\Flysystem" is not installed');
        }
    }

    /**
     * @return void
     */
    public function testFileRead()
    {
        $cmpFile = new File(new Storage\Disk(__DIR__, '/../_examples', 'test.txt'), Constraint::LOOSE);
        $file = new File(new Storage\Flysystem(new Local(__DIR__ . '/../_examples'), 'test.txt'));

        $this->assertTrue($file->isFile());
        $this->assertSame(
            $file->read(),
            file_get_contents(__DIR__ . '/../_examples/test.txt')
        );

        $this->assertSame([
            'type' => 'file',
            'path' => 'test.txt',
            'timestamp' => $cmpFile->getTime(),
            'size' => $cmpFile->getSize(),
        ], $file->storage()->getMetadata());

        $this->assertSame($cmpFile->getTime(), $file->getTime());
        $this->assertSame($cmpFile->getHash(), $file->getHash());
    }

    /**
     * @return void
     */
    public function testDirectoryRead()
    {
        $dir = new Directory(new Storage\Flysystem(new Local(__DIR__ . '/..'), '_examples'));
        $this->assertTrue($dir->isDir());

        $files = [];

        /** @var Directory|File $entry */
        foreach ($dir->list(false)->all() as $file) {

            // skip directories
            if ($file instanceof Directory) {
                continue;
            }

            $files[] = $file;

            $this->assertInstanceOf(File::class, $file);
            $this->assertInstanceOf(Storage\Flysystem::class, $file->storage());
        }

        foreach ($files as $file) {
            $this->assertTrue($file->isFile());
        }
    }
}
