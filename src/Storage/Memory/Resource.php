<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage\Memory;

use ricwein\FileSystem\Storage\Memory;

use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;

/**
 * like Memory, but for temporary files
 */
class Resource extends Memory
{
    /**
     * @param resource $resource
     * @throws UnexpectedValueException
     */
    public function __construct($resource)
    {
        if (!\is_resource($resource)) {
            throw new UnexpectedValueException(sprintf('Argument 1 of Memory\Resource() must be of type resource, %s given', is_object($resource) ? get_class($resource) : gettype($resource)), 500);
        }

        $type = \get_resource_type($resource);

        switch ($type) {
            case 'stream': $this->content = \stream_get_contents($resource); break;
            case 'gd':
                \ob_start();
                \imagepng($resource);
                $this->content = \ob_get_clean();
                break;
            default: throw new UnsupportedException(sprintf('unsupported resource of type %s', $type !== null ? $type : 'NULL'), 500);
        }
    }
}
