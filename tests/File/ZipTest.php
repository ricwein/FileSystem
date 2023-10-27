<?php

/** @noinspection PhpParamsInspection */

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\File;

use Exception;
use League\Flysystem\FilesystemException as FlySystemException;
use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Exceptions\FilesystemException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Storage;
use ZipArchive;

class ZipTest extends TestCase
{
    /**
     * @throws FilesystemException
     * @throws FlySystemException
     */
    public function testSingleFileArchive(): void
    {
        // zip this file
        $file = new File(new Storage\Disk(__FILE__));
        $zip = new File\Zip(new Storage\Disk\Temp());

        $zip->addFile($file)->commit();

        self::assertSame('No error', $zip->getStatus());
        self::assertSame(1, $zip->getFileCount());

        // extract this file again
        $extractDir = new Directory(new Storage\Disk\Temp());
        $zip->extractTo($extractDir->storage());
        self::assertSame(1, iterator_count($extractDir->list()->files()));

        // check for file-consistency
        foreach ($extractDir->list()->files() as $extractedFile) {
            self::assertSame($file->getPath()->getFilename(), $extractedFile->getPath()->getFilename());
            self::assertSame($file->getHash(), $extractedFile->getHash());
        }
    }

    /**
     * @throws FilesystemException
     * @throws FlySystemException
     * @throws Exception
     */
    public function testEncryptedArchive(): void
    {
        // zip and encrypt this file with random password
        $file = new File(new Storage\Disk(__FILE__));
        $zip = new File\Zip(new Storage\Disk\Temp());
        $password = bin2hex(random_bytes(16));
        $zip->withPassword($password)->addFile($file)->commit();

        self::assertSame('No error', $zip->getStatus());
        self::assertSame(1, $zip->getFileCount());

        // extract and decrypt this file again
        $extractDir = new Directory(new Storage\Disk\Temp());
        $zip->withPassword($password)->extractTo($extractDir->storage());
        self::assertSame(1, iterator_count($extractDir->list()->files()));

        // check for file-consistency
        foreach ($extractDir->list()->files() as $extractedFile) {
            self::assertSame($file->getPath()->getFilename(), $extractedFile->getPath()->getFilename());
            self::assertSame($file->getHash(), $extractedFile->getHash());
        }
    }

    /**
     * @throws FilesystemException
     * @throws FlySystemException
     */
    public function testMemoryArchive(): void
    {
        $file = new File(new Storage\Memory(file_get_contents(__FILE__)));
        $zip = new File\Zip(new Storage\Disk\Temp());
        $zip->addFile($file)->commit();

        self::assertSame('No error', $zip->getStatus());
        self::assertSame(1, $zip->getFileCount());

        // extract this file again
        $extractDir = new Directory(new Storage\Disk\Temp());
        $zip->extractTo($extractDir->storage());
        self::assertSame(1, iterator_count($extractDir->list()->files()));

        // check for file-consistency
        foreach ($extractDir->list()->files() as $extractedFile) {
            self::assertSame((new File(new Storage\Disk(__FILE__)))->getHash(), $extractedFile->getHash());
        }
    }

    /**
     * @throws FilesystemException
     * @throws FlySystemException
     */
    public function testDirArchive(): void
    {
        $dir = new Directory(new Storage\Disk(__DIR__, '..', '_examples'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $zip = new File\Zip(new Storage\Disk\Temp());
        $zip->addDirectory($dir)->commit();

        self::assertSame('No error', $zip->getStatus());
        self::assertSame(iterator_count($dir->list()->files()), $zip->getFileCount());

        $extractDir = new Directory(new Storage\Disk\Temp());
        $zip->extractTo($extractDir->storage());

        self::assertSame(iterator_count($dir->list()->files()), iterator_count($extractDir->list(true)->files()));

        $sourceFiles = [];
        foreach ($dir->list()->files() as $file) {
            $sourceFiles[$file->getPath()->getFilename()] = $file->getHash();
        }

        foreach ($extractDir->list()->files() as $file) {
            self::assertSame($sourceFiles[$file->getPath()->getFilename()], $file->getHash());
        }
    }

    /**
     * @throws FilesystemException
     * @throws FlySystemException
     */
    public function testRecursiveDirArchive(): void
    {
        $dir = new Directory(new Storage\Disk(__DIR__, '..'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $zip = new File\Zip(new Storage\Disk\Temp());
        $zip->addDirectory($dir)->commit();

        self::assertSame('No error', $zip->getStatus());
        self::assertSame(iterator_count($dir->list(true)->files()), $zip->getFileCount());

        $extractDir = new Directory(new Storage\Disk\Temp());
        $zip->extractTo($extractDir->storage());
        self::assertSame(iterator_count($dir->list(true)->files()), iterator_count($extractDir->list(true)->files()));

        $sourceFiles = [];
        foreach ($dir->list(true)->files() as $file) {
            $sourceFiles[$file->getPath()->getRelativePathToSafePath()] = $file->getHash();
        }

        foreach ($extractDir->list(true)->files() as $file) {
            self::assertSame($sourceFiles[$file->getPath()->getRelativePathToSafePath()], $file->getHash());
        }
    }

    /**
     * @throws FilesystemException
     * @throws FlySystemException
     */
    public function testMixedArchive(): void
    {
        $zip = new File\Zip(new Storage\Disk\Temp());

        $file = new File(new Storage\Disk(__FILE__));
        $dir = new Directory(new Storage\Disk(__DIR__), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $exampleDir = $dir->dir('../_examples');

        $zip->addFile($file, 'testfile1.php');
        $zip->addFile(new File(new Storage\Memory($file->read())), 'testfile2.php');

        $zip->addDirectoryIterator($dir->list(true));
        $zip->addDirectory($exampleDir);
        $zip->commit();

        self::assertSame(iterator_count($dir->list(true)->files()) + iterator_count($exampleDir->list(true)->files()) + 2, $zip->getFileCount());

        $extractDir = new Directory(new Storage\Disk\Temp());
        $zip->extractTo($extractDir->storage());

        $sourceFiles = [
            'testfile1.php' => $file->getHash(),
            'testfile2.php' => $file->getHash(),
        ];

        foreach ($dir->list(true)->files() as $file) {
            $sourceFiles[$file->getPath()->getRelativePathToSafePath()] = $file->getHash();
        }

        foreach ($exampleDir->list(true)->files() as $file) {
            $sourceFiles["{$exampleDir->getPath()->getBasename()}/{$file->getPath()->getRelativePathToSafePath()}"] = $file->getHash();
        }

        foreach ($extractDir->list(true)->files() as $file) {
            self::assertSame($sourceFiles[$file->getPath()->getRelativePathToSafePath()], $file->getHash());
        }
    }

    /**
     * @throws FilesystemException
     * @throws FlySystemException
     */
    public function testComments(): void
    {
        $zip = new File\Zip(new Storage\Disk\Temp());
        $file = new File(new Storage\Disk(__FILE__));

        $zip->setComment('root comment');
        $zip->addFile($file, 'testfile1.php')->setComment('file comment', 'testfile1.php');
        $zip->addFile($file, 'testfile2.php');
        $zip->commit();

        self::assertSame(2, $zip->getFileCount());

        self::assertSame('root comment', $zip->getComment());
        self::assertSame('file comment', $zip->getComment('testfile1.php'));

        self::assertNull($zip->getComment('testfile2.php'));
        self::assertNull($zip->getComment('testfile3.php'));
    }

    /**
     * @throws FilesystemException
     * @throws FlySystemException
     */
    public function testCompression(): void
    {
        $zip = new File\Zip(new Storage\Disk\Temp());
        $file = new File(new Storage\Disk(__FILE__));

        $zip->addFile($file, 'testfile1.php');
        $zip->setCompression(ZipArchive::CM_STORE);
        $zip->addFile($file, 'testfile2.php');
        $zip->setCompression(ZipArchive::CM_DEFLATE);
        $zip->addFile($file, 'testfile3.php');
        $zip->commit();

        self::assertSame(8, $zip->getStat('testfile1.php')['comp_method']);
        self::assertSame(0, $zip->getStat('testfile2.php')['comp_method']);
        self::assertSame(8, $zip->getStat('testfile3.php')['comp_method']);
    }
}
