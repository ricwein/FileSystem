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
     * @param mixed $resource
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function __construct($resource)
    {
        if (!is_resource($resource)) {
            throw new UnexpectedValueException(sprintf('Argument 1 of Memory\Resource() must be of type resource, %s given', is_object($resource) ? get_class($resource) : gettype($resource)), 500);
        }

        $type = get_resource_type($resource);

        if ($type === 'stream') {

            parent::__construct(stream_get_contents($resource));

        } elseif ($type === 'gd' && function_exists('imagepng')) {

            ob_start();
            imagepng($resource);
            parent::__construct(ob_get_clean());

        } else {

            throw new UnsupportedException(sprintf('unsupported resource of type %s', $type ?? 'NULL'), 500);

        }

    }
}
