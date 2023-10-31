<?php

namespace ricwein\FileSystem\Tests\Storage;

use Generator;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\Storage;

class SerializationTest extends TestCase
{
    /**
     * @throws FilesystemException
     */
    public static function getTestStorages(): Generator
    {
        yield [new Storage\Disk(__FILE__)];
        yield [new Storage\Disk(__DIR__)];
        yield [new Storage\Disk(__DIR__, '../', '_examples')];
        yield [new Storage\Disk(__DIR__, '../', '_examples', 'archive.zip')];
        yield [new Storage\Disk('non-existing.file')];
        yield [new Storage\Disk\Current('composer.json')];
        yield [new Storage\Disk\Temp()];
        yield [new Storage\Memory('something')];
        yield [new Storage\Memory\Resource(fopen(__FILE__, 'rb'))];
        yield [new Storage\Flysystem(new LocalFilesystemAdapter(__DIR__ . '/../_examples'), 'test.txt'), UnsupportedException::class];
        yield [new Storage\Stream(__FILE__), UnsupportedException::class];
    }

    #[DataProvider('getTestStorages')]
    public function testStorageSerialization(Storage\BaseStorage $storage, ?string $exception = null): void
    {
        if ($exception !== null) {
            $this->expectException($exception);
        }

        /** @var Storage\BaseStorage $copy */
        $copy = unserialize(serialize($storage));

        if ($exception === null) {
            self::assertEquals($storage, $copy);
            self::assertSame($storage->getDetails(), $copy->getDetails());
        }
    }
}
