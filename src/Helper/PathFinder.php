<?php

/**
 * @author Richard Weinhold
 */

declare(strict_types=1);

namespace ricwein\FileSystem\Helper;

use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\FileSystem;
use ricwein\FileSystem\Path;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Storage\BaseStorage;
use ricwein\FileSystem\Storage\StorageInterface;

/**
 * creates an absolute path from current-working-directory
 */
final class PathFinder
{
    /**
     * list of possible paths
     * => !not components of a single path!
     * @param string[]|Path[]|StorageInterface[]|FileSystem[] $paths
     * @return BaseStorage
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public static function try(array $paths): StorageInterface
    {
        foreach ($paths as $diskPath) {
            if (is_string($diskPath)) {
                if (file_exists($diskPath)) {
                    return new Storage\Disk($diskPath);
                }
            } elseif ($diskPath instanceof Path) {
                if ($diskPath->isFile() || $diskPath->isDir()) {
                    return new Storage\Disk($diskPath);
                }
            } elseif ($diskPath instanceof StorageInterface) {
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
