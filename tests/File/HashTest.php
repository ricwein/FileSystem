<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\File;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Enum\Hash;
use ricwein\FileSystem\Helper\Constraint;

/**
 * test FileSyst\File bases
 *
 * @author Richard Weinhold
 */
class HashTest extends TestCase
{
    public function testStorageHashes()
    {
        $fileDisk = new File(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $fileMemory = new File(new Storage\Memory($fileDisk->read()));

        $this->assertSame($fileDisk->getHash(), $fileMemory->getHash());
    }

    public function testHashCalculation()
    {
        $fileA = new File(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $fileB = new File(new Storage\Disk(__DIR__, '../_examples', 'test.json'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);

        $this->assertNotSame($fileA->getHash(), $fileB->getHash());
    }

    public function testMemoryHashFilename()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("unable to calculate filepath/name hash for in-memory-files");

        $fileMemory = new File(new Storage\Memory(''));
        $fileMemory->getHash(Hash::FILENAME);
    }

    public function testMemoryHashFilepath()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("unable to calculate filepath/name hash for in-memory-files");

        $fileMemory = new File(new Storage\Memory(''));
        $fileMemory->getHash(Hash::FILEPATH);
    }
}
