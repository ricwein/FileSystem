<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Helper;

/**
 * extension-to-mimetype map
 */
class MimeType
{
    /**
     * map file extensions to mime-type
     * @var array
     */
    public const EXTENSION_MAP = [
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

        'png' => 'image/png',
        'jpe' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'ico' => 'image/vnd.microsoft.icon',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'svg' => 'image/svg+xml',
        'svgz' => 'image/svg+xml',

        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        'exe' => 'application/x-msdownload',
        'msi' => 'application/x-msdownload',
        'cab' => 'application/vnd.ms-cab-compressed',

        'mp3' => 'audio/mpeg',
        'qt' => 'video/quicktime',
        'mov' => 'video/quicktime',

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
}
