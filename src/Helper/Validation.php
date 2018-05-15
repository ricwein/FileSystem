<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Helper;

/**
 * file-path validation class
 */
class Validation
{
    /**
     * no requirements
     * @var int
     */
    public const LOOSE = 0;

    /**
     * the resulting path is inside the first given path,
     * this mitigates /../ -traversion attacks
     * @var int
     */
    public const IN_SAVEPATH = 1;

    /**
     * checks if file is in open_basedir restrictions
     * @var int
     */
    public const IN_OPENBASEDIR = 2;

    /**
     * path must not be a symlink
     * @var int
     */
    public const NO_SYMLINK = 4;

    /**
     * @var Path
     */
    protected $path;

    /**
     * @param Path $path
     */
    public function __construct(Path $path)
    {
        $this->path = $path;
    }

    /**
     * @param  int $requires
     * @return bool
     */
    public function isSave(int $requires = self::IN_SAVEPATH | self::IN_OPENBASEDIR | self::NO_SYMLINK): bool
    {
        // not in open_basedir restrictions
        if (
            ($requires & self::IN_OPENBASEDIR) === self::IN_OPENBASEDIR
            && !$this->path->isInOpenBasedir()
        ) {
            return false;
        }

        // path contains a symlink
        if (
            ($requires & self::NO_SYMLINK) === self::NO_SYMLINK
            && file_exists($this->path->raw)
            && is_link($this->path->raw)
        ) {
            return false;
        }

        // ensure realpath is in original search path (prevent /../ cd's)
        if (
            ($requires & self::IN_SAVEPATH) === self::IN_SAVEPATH
            && $this->path->raw !== $this->path->real
            && strpos($this->path->real, $this->path->savepath) !== 0
        ) {
            return false;
        }

        return true;
    }
}
