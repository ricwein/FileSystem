<?php

namespace ricwein\FileSystem\Helper;

/**
 * @internal
 */
readonly class FileSizeUnit
{
    /**
     * @param float $factor in multiple of bytes
     * @param bool|null $isBinary
     * @param string $symbol
     */
    public function __construct(
        public float $factor,
        public ?bool $isBinary,
        public string $symbol,
    ) {}

    public function getNumber(int|float $bytes, int $decimals = 2, string $decimalSeparator = '.', string $thousandsSeparator = ''): string
    {
        $formattedNumber = number_format(
            num: $bytes / $this->factor,
            decimals: $decimals,
            decimal_separator: $decimalSeparator,
            thousands_separator: $thousandsSeparator,
        );

        $formattedNumber = rtrim($formattedNumber, '0');
        if ($formattedNumber === '') {
            return '0';
        }

        if (!str_ends_with($formattedNumber, $decimalSeparator)) {
            return $formattedNumber;
        }
        return substr($formattedNumber, 0, -1);
    }

    public function getFormatted(int|float $bytes, int $decimals = 2, string $decimalSeparator = '.', string $thousandsSeparator = ''): string
    {
        return "{$this->getNumber($bytes, $decimals, $decimalSeparator, $thousandsSeparator)} $this->symbol";
    }
}
