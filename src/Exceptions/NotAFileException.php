<?php

/**
 * @author Richard Weinhold
 */

declare(strict_types=1);

namespace ricwein\FileSystem\Exceptions;

/**
 * the selected file was not found (mostly for disk-storage)
 */
class NotAFileException extends \UnexpectedValueException implements FilesystemException
{
}
