<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage;

use ricwein\FileSystem\Helper\Stream;

use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Enum\Hash;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Storage\Extensions\Binary;

/**
 * represents a file/directory from in-memory
 */
class Memory extends Storage
{

    /**
     * @var string
     */
    protected $content = '';

    /**
     * @param string|null $content
     */
    public function __construct(?string $content = null)
    {
        if ($content !== null) {
            $this->content = $content;
        }
    }

    /**
     * @inheritDoc
     */
    public function doesSatisfyConstraints(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isFile(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isDir():bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isExecutable(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isSymlink(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isReadable(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isWriteable(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function readFile(?int $offset = null, ?int $length = null, int $mode = LOCK_SH): string
    {
        if ($offset !== null && $length !== null) {
            return mb_substr($this->content, $offset, $length, '8bit');
        }

        return $this->content;
    }

    /**
     * @inheritDoc
     */
    public function streamFile(?int $offset = null, ?int $length = null, int $mode = LOCK_SH): void
    {
        echo $this->readFile($offset, $length, $mode);
    }


    /**
     * @inheritDoc
     */
    public function writeFile(string $content, bool $append = false, int $mode = 0): bool
    {
        if ($append) {
            $this->content .= $content;
        } else {
            $this->content = $content;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function removeFile():bool
    {
        $this->content = '';
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getSize(): int
    {
        return mb_strlen($this->content, '8bit');
    }

    /**
     * @inheritDoc
     */
    public function getFileType(bool $withEncoding = false): string
    {
        return (new \finfo($withEncoding ? FILEINFO_MIME : FILEINFO_MIME_TYPE))->buffer($this->content);
    }

    /**
     * @inheritDoc
     */
    public function getFileHash(int $mode = Hash::CONTENT, string $algo = 'sha256'): string
    {
        switch ($mode) {
            case Hash::CONTENT: return hash($algo, $this->content, false);
            case Hash::FILENAME: case Hash::FILEPATH: throw new RuntimeException('unable to calculate filepath/name hash for in-memory-files', 500);
            default: throw new RuntimeException('unknown hashing-mode', 500);
        }
    }

    /**
     * @inheritDoc
     */
    public function getTime(): int
    {
        return time();
    }

    /**
     * @inheritDoc
     */
    public function touch(bool $ifNewOnly = false, ?int $time = null, ?int $atime = null): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isDotfile(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     * @return Binary\Memory
     */
    public function getHandle(int $mode): Binary
    {
        return new Binary\Memory($this, $mode);
    }

    /**
     * this stream is <b>READONLY!</b>
     * @inheritDoc
     * @throws RuntimeException
     */
    public function getStream(string $mode = 'r+')
    {
        if (!in_array($mode, ['r+','w','w+','a+','x','x+','c+',true])) {
            throw new RuntimeException(sprintf('unable to open stream for memory-storage in non-write mode "%s"', $mode), 500);
        }

        $stream = \fopen('php://memory', $mode);

        if ($stream === false) {
            throw new RuntimeException('failed to open stream', 500);
        }

        // pre-fill stream
        \fwrite($stream, $this->content);
        \rewind($stream);
        return $stream;
    }

    /**
     * @inheritDoc
     * @throws RuntimeException
     */
    public function writeFromStream($stream): bool
    {
        $streamer = new Stream($stream);
        $this->content = $streamer->read();
        return true;
    }

    /**
     * @inheritDoc
     */
    public function copyFileTo(Storage $destination): bool
    {
        switch (true) {

            case $destination instanceof Disk:

                if (!$destination->writeFile($this->readFile())) {
                    return false;
                }
                $destination->path()->reload();
                return true;

            case $destination instanceof Flysystem:
            case $destination instanceof Memory:
            default:
                return $destination->writeFile($this->readFile());
        }
    }

    /**
    * @inheritDoc
     */
    public function moveFileTo(Storage $destination): bool
    {
        return true;
    }
}
