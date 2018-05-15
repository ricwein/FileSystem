<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Helper;

/**
 * file-path validation class
 */
class MimeTye
{
    /**
     * map file extensions to mime-type
     * @var array
     */
    public const EXTENSION_MAP = [
        'json' => 'application/json',
        'yaml' => 'application/x-yaml', 'yml' => 'application/x-yaml',
        'xml' => 'application/xml', 'html' => 'application/xhtml+xml',
        'ini' => 'text/plain',
        'txt' => 'text/plain', 'csv' => 'text/comma-separated-values',
        'php' => 'application/x-httpd-php',
    ];
}
