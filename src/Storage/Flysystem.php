<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage;

use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\AbstractAdapter;
use ricwein\FileSystem\Exception\UnexpectedValueException;

/**
 * represents a file/directory at the local filesystem
 */
class Flysystem extends Storage
{
    /**
     * @var Filesystem
     */
    protected $flysystem;

    /**
     * @param AbstractAdapter|Filesystem $filesystem
     * @throws UnexpectedValueException
     */
    public function __construct($filesystem)
    {
        if ($filesystem instanceof AbstractAdapter) {
            $this->flysystem = new Filesystem($filesystem);
        } elseif ($filesystem instanceof Filesystem) {
            $this->flysystem = $filesystem;
        } else {
            throw new UnexpectedValueException(sprintf('unable to init Flysystem storage-engine from %s', get_class($filesystem)), 500);
        }
    }
}
