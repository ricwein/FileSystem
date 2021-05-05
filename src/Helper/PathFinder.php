<?php

/**
 * @author Richard Weinhold
 */

declare(strict_types=1);

namespace ricwein\FileSystem\Helper;

use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\FileSystem;
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
     * @param string[]|Path[]|Storage[]|FileSystem[] $paths
     * @throws FileNotFoundException
     * @throws UnexpectedValueException
     * @throws RuntimeException
     */
    public static function try(array $paths): Storage
    {
        foreach ($paths as $diskPath) {
            if (is_string($diskPath)) {
                if (file_exists($diskPath)) {
                    return new Storage\Disk($diskPath);
                }
            } elseif ($diskPath instanceof Path) {
                if ($diskPath->fileInfo()->isFile() || $diskPath->fileInfo()->isDir()) {
                    return new Storage\Disk($diskPath);
                }
            } elseif ($diskPath instanceof Storage) {
                if ($diskPath->isFile() || $diskPath->isDir()) {
                    return $diskPath;
                }
            } elseif ($diskPath instanceof FileSystem) {
                if ($diskPath->isFile() || $diskPath->isDir()) {
                    return $diskPath->storage();
                }
            } else {
                throw new UnexpectedValueException(sprintf('invalid search-path of type \'%s\'', get_debug_type($diskPath)), 500);
            }
        }

        throw new FileNotFoundException('file not found', 404);
    }
}
