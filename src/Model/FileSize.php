<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Model;

use JsonSerializable;
use ricwein\FileSystem\Helper\FileSizeUnit;
use Serializable;
use UnexpectedValueException;

final class FileSize implements Serializable, JsonSerializable
{
    /**
     * @var array<FileSizeUnit>|null
     */
    private static ?array $units = null;

    private int|float $bytes;
    private ?FileSizeUnit $unit = null;

    /**
     * @return FileSizeUnit[]
     */
    private static function getUnits(?bool $preferBinarySizes): array
    {
        if (self::$units === null) {
            self::$units = [
                new FileSizeUnit(2 ** 80, true, 'YiB'),
                new FileSizeUnit(1e24, false, 'YB'),
                new FileSizeUnit(2 ** 70, true, 'ZiB'),
                new FileSizeUnit(1e21, false, 'ZB'),
                new FileSizeUnit(2 ** 60, true, 'EiB'),
                new FileSizeUnit(1e18, false, 'EB'),
                new FileSizeUnit(2 ** 50, true, 'PiB',),
                new FileSizeUnit(1e15, false, 'PB'),
                new FileSizeUnit(2 ** 40, true, 'TiB'),
                new FileSizeUnit(1e12, false, 'TB'),
                new FileSizeUnit(2 ** 30, true, 'GiB'),
                new FileSizeUnit(1e9, false, 'GB'),
                new FileSizeUnit(2 ** 20, true, 'MiB'),
                new FileSizeUnit(1e6, false, 'MB'),
                new FileSizeUnit(2 ** 10, true, 'KiB'),
                new FileSizeUnit(1e3, false, 'KB'),
                new FileSizeUnit(1, null, 'B'),
                new FileSizeUnit(1 / 8, null, 'b'), // bit
            ];
        }

        if ($preferBinarySizes === null) {
            return self::$units;
        }

        return array_filter(
            self::$units,
            static fn(FileSizeUnit $unit): bool => $unit->isBinary === null || $unit->isBinary === $preferBinarySizes
        );
    }

    public static function from(null|false|string|int|float $size, bool $preferBinarySizes = false): ?self
    {
        if ($size === null || $size === false) {
            return null;
        }

        if (is_int($size) || is_float($size) || is_numeric($size)) {
            return new self(
                size: $size,
                preferBinarySizes: $preferBinarySizes
            );
        }

        $units = self::getUnits(null);
        $unitChars = [];
        foreach ($units as $unit) {
            $unitChars[] = str_split($unit->symbol);
        }
        $unitChars = implode('', array_unique(array_merge(...$unitChars)));

        if (preg_match("/^(\d+|\d*[.,]\d+)\s*([$unitChars]+)$/i", $size, $matches)) {
            [, $bytes, $symbol] = $matches;

            foreach ($units as $unit) {
                if (strtolower($symbol) === strtolower($unit->symbol)) {
                    return new self(
                        size: (int)($bytes * $unit->factor),
                        preferBinarySizes: $preferBinarySizes
                    );
                }
            }
        }

        return null;
    }

    private function __construct(
        string|int|float $size,
        private readonly bool $preferBinarySizes,
    ) {
        if (is_int($size) || is_float($size)) {
            $this->bytes = $size;
            return;
        }

        $intSize = (int)$size;
        $this->bytes = (((string)$intSize) === $size)
            ? $intSize
            : ((float)$intSize);
    }

    private function getBestUnit(): FileSizeUnit
    {
        if (null !== $unit = $this->unit) {
            return $unit;
        }

        foreach (self::getUnits($this->preferBinarySizes) as $unit) {
            if ($this->bytes / $unit->factor >= 1.0) {
                return $this->unit = $unit;
            }
        }

        return new FileSizeUnit(1.0, null, 'B');
    }

    public function getBytes(): int
    {
        return $this->bytes;
    }

    public function getNumber(int $decimals = 2, string $decimalSeparator = '.', string $thousandsSeparator = ''): string
    {
        return $this->getBestUnit()->getNumber($this->bytes, $decimals, $decimalSeparator, $thousandsSeparator);
    }

    public function getUnit(): string
    {
        return $this->getBestUnit()->symbol;
    }

    public function getFormatted(int $decimals = 2, string $decimalSeparator = '.', string $thousandsSeparator = ''): string
    {
        return $this->getBestUnit()->getFormatted($this->bytes, $decimals, $decimalSeparator, $thousandsSeparator);
    }

    public function getFormattedAs(string|FileSizeUnit $unit, int $decimals = 2, string $decimalSeparator = '.', string $thousandsSeparator = ''): string
    {
        if ($unit instanceof FileSizeUnit) {
            return $unit->getFormatted($this->bytes, $decimals, $decimalSeparator, $thousandsSeparator);
        }

        foreach (self::getUnits(null) as $knownUnit) {
            if (strtolower($unit) === strtolower($knownUnit->symbol)) {
                return $knownUnit->getFormatted($this->bytes, $decimals, $decimalSeparator, $thousandsSeparator);
            }
        }

        throw new UnexpectedValueException("Failed to format file-size to unknown unit '$unit'.", 500);
    }

    public function __toString(): string
    {
        return $this->getFormatted();
    }

    public function serialize(): string
    {
        return serialize($this->__serialize());
    }

    public function unserialize(string $data): void
    {
        $this->__unserialize(unserialize($data, self::class));
    }

    public function __serialize(): array
    {
        return [$this->bytes, $this->preferBinarySizes];
    }

    public function __unserialize(array $data): void
    {
        [$this->bytes, $this->preferBinarySizes] = $data;
    }

    public function jsonSerialize(): array
    {
        return [
            'bytes' => $this->bytes,
            'number' => $this->getNumber(),
            'unit' => $this->getUnit(),
        ];
    }
}
