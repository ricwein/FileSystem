<?php

namespace Helper;

use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Helper\FileSize;

class FileSizeTest extends TestCase
{
    public static function getTestData(): Generator
    {
        yield [0, true, 0, '0 B'];
        yield [1024, true, 1024, '1 KiB'];
        yield [1000, false, 1000, '1 KB'];
        yield [1024, false, 1024, '1.02 KB'];
        yield [1000, true, 1000, '1000 B'];
        yield ['1kiB', true, 1024, '1 KiB'];
        yield ['1kB', true, 1000, '1000 B'];
        yield [2 ** 10, true, 1024, '1 KiB'];
        yield [2 ** 20, true, 1_048_576, '1 MiB'];
        yield ['1MiB', true, 1_048_576, '1 MiB'];
        yield ['1 MiB', true, 1_048_576, '1 MiB'];
        yield ['1  MiB', true, 1_048_576, '1 MiB'];
        yield ['1024 kiB', true, 1_048_576, '1 MiB'];
    }

    #[DataProvider('getTestData')]
    public function testFileSizeParsing(string|int $in, bool $binary, int $expectedBytes, string $expectedFormat): void
    {
        $size = FileSize::from($in, $binary);

        self::assertNotNull($size);
        self::assertSame($expectedBytes, $size->getBytes());
        self::assertSame($expectedFormat, (string)$size);
    }
}
