<?php
/**
 * @author Richard Weinhold
 */

namespace ricwein\FileSystem\Helper;

use ricwein\FileSystem\Exceptions\RuntimeException;

/**
 * creates a absolute path from current-working-directory
 */
class Stream
{

    /** @var resource $handle */
    protected $handle;
    protected bool $closeOnFree = false;
    protected ?int $lock = null;

    /**
     * @param resource $handle
     * @throws RuntimeException
     */
    public function __construct($handle)
    {
        if (!is_resource($handle)) {
            throw new RuntimeException(sprintf('file-handle must be of type \'resource\' but \'%s\' given', is_object($handle) ? get_class($handle) : gettype($handle)), 500);
        }

        $this->handle = $handle;
    }

    /**
     * @param string $filename
     * @param string $mode
     * @return static
     * @throws RuntimeException
     */
    public static function fromResourceName(string $filename, string $mode = 'rb+'): self
    {
        if (false === $handle = @fopen($filename, $mode)) {
            throw new RuntimeException("stream creation failed, resource not found: {$filename}", 500);
        }

        $stream = new static($handle);
        $stream->closeOnFree(true);

        return $stream;
    }

    /**
     */
    public function __destruct()
    {
        if ($this->lock !== null) {
            flock($this->handle, LOCK_UN);
        }

        if ($this->closeOnFree && is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    /**
     * @param int $mode
     * @return $this
     * @throws RuntimeException
     */
    public function lock(int $mode): self
    {
        if ($mode !== 0 && !flock($this->handle, $mode | LOCK_NB)) {
            throw new RuntimeException('unable to get file-lock', 500);
        }

        $this->lock = $mode;
        return $this;
    }

    /**
     * @param int $offset
     * @return self
     * @throws RuntimeException
     */
    public function rewind(int $offset = 0): self
    {
        if (fseek($this->handle, 0) !== 0) {
            throw new RuntimeException('error while rewinding file', 500);
        }

        return $this;
    }

    /**
     * auto-close stream on destruction
     * @param bool $activate
     * @return self
     */
    public function closeOnFree(bool $activate = true): self
    {
        $this->closeOnFree = $activate;
        return $this;
    }

    /**
     * @param string $algo
     * @param bool $raw
     * @return string
     * @throws RuntimeException
     */
    public function getHash(string $algo = 'sha256', bool $raw = false): string
    {
        $this->rewind();

        $hc = hash_init($algo);
        hash_update_stream($hc, $this->handle);
        return hash_final($hc, $raw);
    }

    /**
     * @param int $offset
     * @param int|null $length
     * @return string
     * @throws RuntimeException
     */
    public function read(int $offset = 0, ?int $length = null): string
    {
        $this->rewind($offset);

        if ($length === null) {
            $filesize = fstat($this->handle)['size'] ?? 0;
            $length = $filesize - $offset;
        }

        if ($length < 0) {
            return '';
        }

        // read part of file
        if (false === $result = fread($this->handle, $length)) {
            throw new RuntimeException('error while reading file', 500);
        }

        return $result;
    }

    /**
     * @param resource $handle
     * @param int $offset
     * @param int|null $length
     * @throws RuntimeException
     */
    public function copyTo($handle, int $offset = 0, ?int $length = null): void
    {
        if (false === stream_copy_to_stream($this->handle, $handle, $length, $offset)) {
            throw new RuntimeException('error while copying to stream', 500);
        }
    }

    /**
     * @param resource $handle
     * @param int $offset
     * @param int|null $length
     * @throws RuntimeException
     */
    public function copyFrom($handle, int $offset = 0, ?int $length = null): void
    {
        if (false === stream_copy_to_stream($handle, $this->handle, $length, $offset)) {
            throw new RuntimeException('error while copying from stream', 500);
        }
    }

    /**
     * @param Stream $stream
     * @param int $offset
     * @param int|null $length
     * @throws RuntimeException
     */
    public function copyToStream(self $stream, int $offset = 0, ?int $length = null): void
    {
        $this->copyTo($stream->getHandle(), $offset, $length);
    }

    /**
     * @return resource
     */
    public function getHandle()
    {
        return $this->handle;
    }

    /**
     * @param int $offset
     * @param int|null $length
     * @param int $bufferSize
     * @return void
     * @throws RuntimeException
     */
    public function passthru(int $offset = 0, ?int $length = null, int $bufferSize = 1024): void
    {
        $this->rewind($offset);

        if ($length === null) {

            if (fpassthru($this->handle) !== false) {
                flush();
                return;
            }

            throw new RuntimeException('error while reading file', 500);
        }

        $remaining = $length;

        while ($remaining > 0 && !feof($this->handle)) {
            $readLength = ($remaining > $bufferSize) ? $bufferSize : $remaining;
            $remaining -= $readLength;

            if (false !== $result = fread($this->handle, $readLength)) {
                echo $result;
                flush();
            } else {
                throw new RuntimeException('error while reading file', 500);
            }
        }
    }
}
