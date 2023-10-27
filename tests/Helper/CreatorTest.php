<?php

namespace ricwein\FileSystem\Tests\Helper;

use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Helper\Creator;
use SplFileInfo;

class CreatorTest extends TestCase
{
    public static function provideTestData(): Generator
    {
        yield [new SplFileInfo('non-existing-file'), 'null'];
        yield [new SplFileInfo(__FILE__), File::class];
        yield [new SplFileInfo(__DIR__), Directory::class];
        yield [new SplFileInfo(__DIR__ . '/../_examples/'), Directory::class];
        yield [new SplFileInfo(__DIR__ . '/../_examples/test.txt'), File::class];
        yield [new SplFileInfo(__DIR__ . '/non-existing-dir/'), 'null'];
    }

    #[DataProvider('provideTestData')]
    public function testCreator(SplFileInfo $fileInfo, string $excepted): void
    {
        self::assertSame($excepted, get_debug_type(Creator::fromFileInfo($fileInfo)));
    }
}
