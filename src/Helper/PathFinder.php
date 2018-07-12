<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Helper;

use ricwein\FileSystem\Storage;
use ricwein\FileSystem\FileSystem;
use ricwein\FileSystem\Helper\Path;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Exceptions\FileNotFoundException;

/**
 * creates a absolute path from current-working-directory
 */
class PathFinder
{
    /**
     * list of possible paths
     * => !not components of a single path!
     * @param  string[]|Path[]|Storage[]|FileSystem[] $paths
     * @return Storage
     */
    public static function try(array $paths): Storage
    {
        foreach ($paths as $diskpath) {
            if (is_string($diskpath)) {
                if (file_exists($diskpath)) {
                    return new Storage\Disk($diskpath);
                }
            } elseif ($diskpath instanceof Path) {
                if ($diskpath->fileInfo()->isFile() || $diskpath->fileInfo()->isDir()) {
                    return new Storage\Disk($diskpath);
                }
            } elseif ($diskpath instanceof Storage) {
                if ($diskpath->isFile() || $diskpath->isDir()) {
                    return $diskpath;
                }
            } elseif ($diskpath instanceof FileSystem) {
                if ($diskpath->isFile() || $diskpath->isDir()) {
                    return $diskpath->storage();
                }
            } else {
                throw new UnexpectedValueException(sprintf('invalid search-path of type \'%s\'', is_object($diskpath) ? get_class($diskpath) : gettype($diskpath)), 500);
            }
        }

        throw new FileNotFoundException('file not found', 404);
    }
}
