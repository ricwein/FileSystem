<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage\Extensions\Binary;

use ricwein\FileSystem\Storage\Memory as MemoryStorage;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Storage\Extensions\Binary;

/**
 * @inheritDoc
 */
class Memory extends Binary
{

    /**
     * @var MemoryStorage
     */
    protected $storage;

    /**
     * @param MemoryStorage $storage
     */
    public function __construct(MemoryStorage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * @inheritDoc
     */
    public function writeBytes(string $buf, ?int $num = null): int
    {
        if (!$this->storage->writeFile($buf, true)) {
            return 0;
        }

        $length = mb_strlen($buf, '8bit');
        $this->pos = $length;

        return $length;
    }

    /**
     * @inheritDoc
     */
    public function readBytes(int $num): string
    {
        if ($num < 0) {
            throw new RuntimeException('invalid byte-count', 500);
        } elseif ($num === 0) {
            return '';
        }

        if (($this->pos + $num) > $this->storage->getSize()) {
            throw new RuntimeException('Out-of-bounds read', 500);
        }

        $buf = '';
        $remaining = $num;

        $buf = $this->storage->readFile($this->pos, $num);
        $this->pos += $num;

        return $buf;
    }

    /**
     * @inheritDoc
     */
    public function remainingBytes(): int
    {
        return (int) (PHP_INT_MAX & ((int) $this->storage->getSize() - $this->pos));
    }

    /**
     * @inheritDoc
     */
    public function reset(int $position = 0): bool
    {
        $this->pos = $position;
        return true;
    }
}
