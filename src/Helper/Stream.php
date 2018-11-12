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
    /**
     * @var resource
     */
    protected $handle;

    /**
     * @var bool
     */
    protected $closeOnFree = false;

    /**
     * @param resource $handle
     */
    public function __construct($handle)
    {
        if (!is_resource($handle)) {
            throw new RuntimeException(sprintf('file-handle must be of type \'resource\' but \'%s\' given', is_object($handle) ? get_class($handle) : gettype($handle)), 500);
        }

        $this->handle = $handle;
    }

    /**
     */
    public function __destruct()
    {
        if ($this->closeOnFree && is_resource($this->handle)) {
            \fclose($this->handle);
        }
    }

    /**
     * @throws RuntimeException
     * @return self
     */
    public function rewind(): self
    {
        if (\fseek($this->handle, 0) !== 0) {
            throw new RuntimeException('error while rewinding file', 500);
        }

        return $this;
    }

    /**
     * auto-close stream on destruction
     * @param  bool $activate
     * @return self
     */
    public function closeOnFree(bool $activate = true): self
    {
        $this->closeOnFree = $activate;
        return $this;
    }

    /**
     * [hash description]
     * @param  string $algo [description]
     * @return string       [description]
     */
    public function hash(string $algo = 'sha256'): string
    {
        $this->rewind();

        $hc = \hash_init($algo);
        \hash_update_stream($hc, $this->handle);
        return \hash_final($hc);
    }

    /**
     * @param  int|null $offset
     * @param  int|null $length
     * @throws RuntimeException
     * @return string
     */
    public function read(?int $offset = null, ?int $length = null): string
    {
        if (($offset === null || $length === null)) {
            $this->rewind();

            if (0 < $filesize = \fstat($this->handle)['size'] ?? 0) {
                if (false !== $buffer = \fread($this->handle, $filesize)) {
                    return $buffer;
                }
                throw new RuntimeException('error while reading file', 500);
            }

            return '';
        }

        if (\fseek($this->handle, $offset) !== 0) {
            throw new RuntimeException('error while seeking file', 500);
        }

        // read part of file
        if (false !== $result = \fread($this->handle, $length)) {
            return $result;
        }

        throw new RuntimeException('error while reading file', 500);
    }

    /**
     * @param  int|null $offset
     * @param  int|null $length
     * @param  int      $bufferSize
     * @throws RuntimeException
     * @return void
     */
    public function send(?int $offset = null, ?int $length = null, int $bufferSize = 1024): void
    {
        if ($offset === null || $length === null) {
            $this->rewind();

            if (\fpassthru($this->handle) !== false) {
                \flush();
                return;
            }

            throw new RuntimeException('error while reading file', 500);
        }

        if (\fseek($this->handle, $offset) !== 0) {
            throw new RuntimeException('error while seeking file', 500);
        }

        $remaining = $length;

        while ($remaining > 0 && !\feof($this->handle)) {
            $readLength = ($remaining > $bufferSize) ? $bufferSize : $remaining;
            $remaining -= $readLength;

            if (false !== $result = \fread($this->handle, $readLength)) {
                echo $result;
                \flush();
            } else {
                throw new RuntimeException('error while reading file', 500);
            }
        }
    }
}
