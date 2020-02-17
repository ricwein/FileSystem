<?php

/**
 * @author Richard Weinhold
 */

namespace ricwein\FileSystem\Storage\Extensions\Binary;

use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
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
    protected MemoryStorage $storage;

    /**
     * @inheritDoc
     * @param MemoryStorage $storage
     */
    public function __construct(int $mode, MemoryStorage $storage)
    {
        parent::__construct($mode);
        $this->storage = $storage;
    }

    /**
     * @inheritDoc
     * @throws AccessDeniedException
     */
    public function write(string $bytes, ?int $length = null): int
    {
        $this->applyAccessMode(self::MODE_WRITE);

        if (!$this->storage->writeFile($bytes, true)) {
            return 0;
        }

        $length = mb_strlen($bytes, '8bit');
        $this->pos += $length;

        return $length;
    }

    /**
     * @inheritDoc
     * @throws AccessDeniedException
     * @throws FileNotFoundException
     */
    public function read(int $length): string
    {
        $this->applyAccessMode(self::MODE_READ);

        if ($length < 0) {
            throw new RuntimeException('invalid byte-count', 500);
        } elseif ($length === 0) {
            return '';
        }

        if (($this->pos + $length) > $this->storage->getSize()) {
            throw new RuntimeException('Out-of-bounds read', 500);
        }

        $buf = $this->storage->readFile($this->pos, $length);
        $this->pos += $length;

        return $buf;
    }

    /**
     * @inheritDoc
     */
    public function getSize(): int
    {
        return $this->storage->getSize();
    }

    /**
     * @inheritDoc
     */
    public function seek(int $position = 0): bool
    {
        $this->pos = $position;
        return true;
    }
}
