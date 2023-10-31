<?php

namespace ricwein\FileSystem\Storage;

use ricwein\FileSystem\Enum\Hash;
use ricwein\FileSystem\Enum\Time;
use ricwein\FileSystem\Helper\Stream as StreamResource;
use ricwein\FileSystem\Storage\Extensions\Binary;

interface FileStorageInterface extends StorageInterface
{
    /**
     * access file for binary read/write actions
     * @internal
     */
    public function getHandle(int $mode): Binary;

    /**
     * @internal
     */
    public function getStream(string $mode = 'rb+'): StreamResource;


    /**
     * check if file exists and is executable
     * @internal
     */
    public function isExecutable(): bool;

    /**
     * @internal
     */
    public function readFile(int $offset = 0, ?int $length = null, int $mode = LOCK_SH): string;

    /**
     * @return string[]
     * @internal
     */
    public function readFileAsLines(): array;

    /**
     * @internal
     */
    public function streamFile(int $offset = 0, ?int $length = null, int $mode = LOCK_SH): void;


    /**
     * Update content from stream.
     * @param StreamResource $stream file-handle
     * @internal
     */
    public function writeFromStream(StreamResource $stream): bool;

    /**
     * write content to storage
     * @param int $mode LOCK_EX
     * @internal
     */
    public function writeFile(string $content, bool $append = false, int $mode = 0): bool;

    /**
     * remove file from storage
     * @internal
     */
    public function removeFile(): bool;


    /**
     * @param null|int $time last-modified time
     * @param null|int $atime last-access time
     * @internal
     */
    public function touch(bool $ifNewOnly = false, ?int $time = null, ?int $atime = null): bool;

    /**
     * Calculate file-hash.
     * @param string $algo hashing-algorithm
     * @internal
     */
    public function getFileHash(Hash $mode = Hash::CONTENT, string $algo = 'sha256', bool $raw = false): ?string;

    /**
     * get last-modified timestamp
     * @internal
     */
    public function getTime(Time $type = Time::LAST_MODIFIED): ?int;

    /**
     * guess content-type (mime) of file
     * @internal
     */
    public function getFileType(bool $withEncoding = false): ?string;


    /**
     * <b>Copy</b> file to new destination.
     * @internal
     */
    public function copyFileTo(BaseStorage $destination): bool;

    /**
     * <b>Move</b> file to new destination.
     * @internal
     */
    public function moveFileTo(BaseStorage $destination): bool;
}
