<?php
declare(strict_types=1);

namespace ricwein\FileSystem\Storage\Extensions\Binary;

use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Helper\Stream as StreamResource;
use ricwein\FileSystem\Storage\Extensions\Binary;
use ricwein\FileSystem\Storage\Stream as StreamStorage;

class Stream extends Binary
{
    protected StreamResource $stream;

    /**
     * @inheritDoc
     */
    public function __construct(int $mode, StreamStorage $storage)
    {
        parent::__construct($mode);
        $this->stream = $storage->getStream();
    }

    /**
     * @inheritDoc
     * @throws RuntimeException
     * @throws AccessDeniedException
     */
    public function write(string $bytes, ?int $length = null): int
    {
        $this->applyAccessMode(self::MODE_WRITE);

        $this->stream->write($bytes);

        $writtenLength = mb_strlen($bytes, '8bit');
        $this->pos += $writtenLength;

        return $writtenLength;
    }

    /**
     * @inheritDoc
     * @throws RuntimeException
     * @throws AccessDeniedException
     */
    public function read(int $length): string
    {
        $this->applyAccessMode(self::MODE_READ);

        if ($length < 0) {
            throw new RuntimeException('invalid byte-count', 500);
        }

        if ($length === 0) {
            return '';
        }

        if (($this->pos + $length) > $this->stream->getSize()) {
            throw new RuntimeException('Out-of-bounds read', 500);
        }

        $buf = $this->stream->read($this->pos, $length);
        $this->pos += $length;

        return $buf;
    }

    /**
     * @inheritDoc
     */
    public function getSize(): int
    {
        return $this->stream->getSize();
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
