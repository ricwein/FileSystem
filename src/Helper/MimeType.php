<?php

/**
 * @author Richard Weinhold
 */

declare(strict_types=1);

namespace ricwein\FileSystem\Helper;

/**
 * extension-to-mimetype map
 */
final class MimeType
{
    /**
     * map general file extensions to mime-type
     */
    protected const EXTENSIONS = [
        'json' => 'application/json',
        'yaml' => 'application/x-yaml',
        'yml' => 'application/x-yaml',
        'ini' => 'text/plain',
        'txt' => 'text/plain',
        'csv' => 'text/comma-separated-values',
        'php' => 'application/x-httpd-php',

        'htm' => 'text/html',
        'html' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'xml' => 'application/xml',
        'swf' => 'application/x-shockwave-flash',
        'flv' => 'video/x-flv',

        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        'exe' => 'application/x-msdownload',
        'msi' => 'application/x-msdownload',
        'cab' => 'application/vnd.ms-cab-compressed',

        'pdf' => 'application/pdf',
        'psd' => 'image/vnd.adobe.photoshop',
        'ai' => 'application/postscript',
        'eps' => 'application/postscript',
        'ps' => 'application/postscript',

        'doc' => 'application/msword',
        'rtf' => 'application/rtf',
        'xls' => 'application/vnd.ms-excel',
        'ppt' => 'application/vnd.ms-powerpoint',
    ];

    /**
     * map image extensions to mime-type
     */
    protected const EXTENSIONS_IMAGES = [
        'png' => 'image/png',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'ico' => 'image/vnd.microsoft.icon',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'svg' => 'image/svg+xml',
        'svgz' => 'image/svg+xml',
    ];

    /**
     * map video extensions to mime-type
     */
    protected const EXTENSIONS_VIDEOS = [
        'mp3' => 'audio/mpeg',
        'qt' => 'video/quicktime',
        'mov' => 'video/quicktime',
    ];

    /**
     * @return string[]
     */
    protected static function getExtensions(): array
    {
        static $extensions = null;

        if ($extensions === null) {
            $extensions = array_merge(
                static::EXTENSIONS,
                static::EXTENSIONS_IMAGES,
                static::EXTENSIONS_VIDEOS
            );
        }

        return $extensions;
    }

    /**
     * fetch file-extension for given mimetype
     */
    public static function getExtensionFor(string $mimetype): ?string
    {
        $extensions = static::getExtensions();
        if (false !== $match = array_search($mimetype, $extensions, true)) {
            return $match;
        }

        return null;
    }

    /**
     * fetch mimetype for given file-extension
     */
    public static function getMimeFor(string $extension): ?string
    {
        $extensions = static::getExtensions();
        return $extensions[$extension] ?? null;
    }

    public static function isImage(string $mimetype): bool
    {
        return in_array($mimetype, static::EXTENSIONS_IMAGES, true);
    }

    public static function isVideo(string $mimetype): bool
    {
        return in_array($mimetype, static::EXTENSIONS_VIDEOS, true);
    }
}
