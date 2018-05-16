<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Helper;

/**
 * file-path validation class
 */
class Constraint
{
    /**
     * no requirements
     * @var int
     */
    public const LOOSE = 0000;

    /**
     * the resulting path is inside the first given path,
     * this mitigates /../ -traversion attacks
     * @var int
     */
    public const IN_SAVEPATH = 0001;

    /**
     * checks if file is in open_basedir restrictions
     * @var int
     */
    public const IN_OPENBASEDIR = 0002;

    /**
     * path must not be a symlink
     * @var int
     */
    public const DISALLOW_LINK = 004;

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
     * @param  int $contraints
     * @return bool
     */
    public function doesSatisfy(int $contraints = self::IN_SAVEPATH | self::IN_OPENBASEDIR | self::DISALLOW_LINK): bool
    {
        // not in open_basedir restrictions
        if (
            ($contraints & self::IN_OPENBASEDIR) === self::IN_OPENBASEDIR
            && !$this->path->isInOpenBasedir()
        ) {
            return false;
        }

        // path contains a symlink
        if (
            ($contraints & self::DISALLOW_LINK) === self::DISALLOW_LINK
            && file_exists($this->path->raw)
            && is_link($this->path->raw)
        ) {
            return false;
        }

        // ensure realpath is in original search path (prevent /../ cd's)
        if (
            ($contraints & self::IN_SAVEPATH) === self::IN_SAVEPATH
            && $this->path->raw !== $this->path->real
            && strpos($this->path->real, $this->path->savepath) !== 0
        ) {
            return false;
        }

        return true;
    }
}
