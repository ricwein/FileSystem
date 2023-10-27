<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\File;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Enum\Hash;
use ricwein\FileSystem\Exceptions\FilesystemException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Storage;

class HashTest extends TestCase
{
    /**
     * @throws FilesystemException
     */
    public function testStorageHashes(): void
    {
        $fileDisk = new File(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $fileMemory = new File(new Storage\Memory($fileDisk->read()));

        self::assertSame($fileDisk->getHash(), $fileMemory->getHash());
    }

    /**
     * @throws FilesystemException
     */
    public function testHashCalculation(): void
    {
        $fileA = new File(new Storage\Disk(__DIR__, '../_examples', 'test.txt'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
        $fileB = new File(new Storage\Disk(__DIR__, '../_examples', 'test.json'), Constraint::STRICT & ~Constraint::IN_SAFEPATH);

        self::assertNotSame($fileA->getHash(), $fileB->getHash());
    }

    /**
     * @throws FilesystemException
     */
    public function testMemoryHashFilename(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("unable to calculate filepath/name hash for in-memory-files");

        $fileMemory = new File(new Storage\Memory(''));
        $fileMemory->getHash(Hash::FILENAME);
    }

    /**
     * @throws FilesystemException
     */
    public function testMemoryHashFilepath(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("unable to calculate filepath/name hash for in-memory-files");

        $fileMemory = new File(new Storage\Memory(''));
        $fileMemory->getHash(Hash::FILEPATH);
    }
}
