<?php

namespace ricwein\FileSystem\Helper;

use ricwein\FileSystem\Directory;
use ricwein\FileSystem\File;
use ricwein\FileSystem\FileSystem;
use ricwein\FileSystem\Storage;
use SplFileInfo;

class Creator
{
    public static function fromFileInfo(SplFileInfo $fileInfo, int $constraint = Constraint::STRICT): ?FileSystem
    {
        $storage = new Storage\Disk($fileInfo);
        return match (true) {
            $storage->isDir() => new Directory($storage, $constraint),
            $storage->isFile() => new File($storage, $constraint),
            default => null,
        };
    }
}
