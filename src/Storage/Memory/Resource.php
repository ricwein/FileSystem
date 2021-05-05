<?php
/**
 * @author Richard Weinhold
 * @noinspection PhpComposerExtensionStubsInspection
 */

declare(strict_types=1);

namespace ricwein\FileSystem\Storage\Memory;

use GdImage;
use ricwein\FileSystem\Storage\Memory;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;

/**
 * like Memory, but for temporary files
 */
class Resource extends Memory
{
    /**
     * @param GdImage|mixed $resource
     * @throws UnexpectedValueException
     * @throws UnsupportedException
     */
    public function __construct(mixed $resource)
    {
        switch (true) {
            case $resource instanceof GdImage:
                ob_start();
                imagepng($resource);
                parent::__construct(ob_get_clean());
                break;

            case is_resource($resource):
                $type = get_resource_type($resource);
                if ($type === 'stream') {
                    parent::__construct(stream_get_contents($resource));
                    break;
                }

                throw new UnsupportedException(sprintf('unsupported resource of type %s', $type ?? 'NULL'), 500);

            default:
                throw new UnexpectedValueException(sprintf('Argument 1 of Memory\Resource() must be of type resource|GdImage, but %s given', get_debug_type($resource)), 500);

        }
    }
}
