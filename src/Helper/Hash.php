<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Helper;

/**
 * file-path validation class
 */
class Hash
{
    /**
     * hash over filename only
     * @var int
     */
    const FILENAME = 1;

    /**
     * hash over full filepath
     * @var int
     */
    const FILEPATH = 2;

    /**
     * hash over file-content
     * @var int
     */
    const CONTENT  = 4;
}
