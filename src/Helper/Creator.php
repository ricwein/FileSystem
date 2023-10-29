<?php

namespace ricwein\FileSystem\Helper;

use ricwein\FileSystem\Directory;
use ricwein\FileSystem\File;
use ricwein\FileSystem\FileSystem;
use ricwein\FileSystem\Storage;
use SplFileInfo;

final class Creator
{
    public static function from(SplFileInfo|Storage\StorageInterface $fileInfo, int $constraint = Constraint::STRICT): ?FileSystem
    {
        $storage = $fileInfo instanceof SplFileInfo ? new Storage\Disk($fileInfo) : $fileInfo;
        return match (true) {
            $storage->isDir() => new Directory($storage, $constraint),
            $storage->isFile() => new File($storage, $constraint),
            default => null,
        };
    }
}
