<?php declare(strict_types = 1);

namespace ricwein\FileSystem\Tests\File;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Directory;

/**
 * test FileSyst\File bases
 *
 * @author Richard Weinhold
 */
class ZipTest extends TestCase
{
    /**
     * @return void
     */
    public function testSingleFileArchive()
    {
        // zip this file
        $file = new File(new Storage\Disk(__FILE__));
        $zip = new File\Zip(new Storage\Disk\Temp);
        $zip->addFile($file)->commit();

        $this->assertSame('No error', $zip->getStatus());

        // extract this file again
        $extractDir = new Directory(new Storage\Disk\Temp);
        $zip->extractTo($extractDir->storage());
        $this->assertSame(1, iterator_count($extractDir->list()->files()));

        // check for file-consistency
        foreach ($extractDir->list()->files() as $extractedFile) {
            $this->assertSame($file->path()->filename, $extractedFile->path()->filename);
            $this->assertSame($file->getHash(), $extractedFile->getHash());
        }
    }

    /**
     * @return void
     */
    public function testEncryptedArchive()
    {
        // zip and encrypt this file with random password
        $file = new File(new Storage\Disk(__FILE__));
        $zip = new File\Zip(new Storage\Disk\Temp);
        $password = bin2hex(random_bytes(16));
        $zip->withPassword($password)->addFile($file)->commit();

        $this->assertSame('No error', $zip->getStatus());

        // extract and decrypt this file again
        $extractDir = new Directory(new Storage\Disk\Temp);
        $zip->withPassword($password)->extractTo($extractDir->storage());
        $this->assertSame(1, iterator_count($extractDir->list()->files()));

        // check for file-consistency
        foreach ($extractDir->list()->files() as $extractedFile) {
            $this->assertSame($file->path()->filename, $extractedFile->path()->filename);
            $this->assertSame($file->getHash(), $extractedFile->getHash());
        }
    }

    /**
     * @return void
     */
    public function testMemoryArchive()
    {
        $file = new File(new Storage\Memory(file_get_contents(__FILE__)));
        $zip = new File\Zip(new Storage\Disk\Temp);
        $zip->addFile($file)->commit();

        $this->assertSame('No error', $zip->getStatus());

        // extract this file again
        $extractDir = new Directory(new Storage\Disk\Temp);
        $zip->extractTo($extractDir->storage());
        $this->assertSame(1, iterator_count($extractDir->list()->files()));

        // check for file-consistency
        foreach ($extractDir->list()->files() as $extractedFile) {
            $this->assertSame((new File(new Storage\Disk(__FILE__)))->getHash(), $extractedFile->getHash());
        }
    }

    /**
     * @return void
     */
    public function testDirArchive()
    {
        $dir = new Directory(new Storage\Disk(__DIR__, '..', '_examples'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $zip = new File\Zip(new Storage\Disk\Temp);
        $zip->addDirectory($dir)->commit();

        $this->assertSame('No error', $zip->getStatus());

        $extractDir = new Directory(new Storage\Disk\Temp);
        $zip->extractTo($extractDir->storage());
        $this->assertSame(iterator_count($dir->list(true)->files()), iterator_count($extractDir->list(true)->files()));

        $sourceFiles = [];
        foreach ($dir->list(true)->files() as $file) {
            $sourceFiles[$file->path()->filename] = $file->getHash();
        }

        foreach ($extractDir->list(true)->files() as $file) {
            $this->assertSame($sourceFiles[$file->path()->filename], $file->getHash());
        }
    }

    /**
     * @return void
     */
    public function testRecursiveDirArchive()
    {
        $dir = new Directory(new Storage\Disk(__DIR__, '..'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $zip = new File\Zip(new Storage\Disk\Temp);
        $zip->addDirectory($dir)->commit();

        $this->assertSame('No error', $zip->getStatus());

        $extractDir = new Directory(new Storage\Disk\Temp);
        $zip->extractTo($extractDir->storage());
        $this->assertSame(iterator_count($dir->list(true)->files()), iterator_count($extractDir->list(true)->files()));

        $sourceFiles = [];
        foreach ($dir->list(true)->files() as $file) {
            $sourceFiles[$file->path()->filepath] = $file->getHash();
        }

        foreach ($extractDir->list(true)->files() as $file) {
            $this->assertSame($sourceFiles[$file->path()->filepath], $file->getHash());
        }
    }

    /**
     * @return void
     */
    public function testMixedArchive()
    {
        $zip = new File\Zip(new Storage\Disk\Temp);

        $file = new File(new Storage\Disk(__FILE__));
        $dir = new Directory(new Storage\Disk(__DIR__, '..'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);

        $zip->addFile($file, 'testfile1.php');
        $zip->addFile(new File(new Storage\Memory($file->read())), 'testfile2.php');
        $zip->addDirectory($dir);
        $zip->commit();

        $extractDir = new Directory(new Storage\Disk\Temp);
        $zip->extractTo($extractDir->storage());

        $sourceFiles = [
            '/testfile1.php' => $file->getHash(),
            '/testfile2.php' => $file->getHash(),
        ];

        foreach ($dir->list(true)->files() as $file) {
            $sourceFiles[$file->path()->filepath] = $file->getHash();
        }

        foreach ($extractDir->list(true)->files() as $file) {
            $this->assertSame($sourceFiles[$file->path()->filepath], $file->getHash());
        }

        fwrite(STDERR, print_r(['zip-path' => $zip->path()->real], true));
        $zip->removeOnFree(false);
    }
}
