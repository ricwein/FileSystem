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
    public const FILENAME = 0b0001;

    /**
     * hash over full filepath
     * @var int
     */
    public const FILEPATH = 0b0010;

    /**
     * hash over file-content
     * @var int
     */
    public const CONTENT  = 0b0100;
}
