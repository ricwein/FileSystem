<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Helper;

/**
 * enum-like hash-type consts
 */
class Hash
{
    /**
     * hash over filename only
     * @var int
     */
    public const FILENAME = 1;

    /**
     * hash over full filepath
     * @var int
     */
    public const FILEPATH = 2;

    /**
     * hash over file-content
     * @var int
     */
    public const CONTENT  = 4;
}
