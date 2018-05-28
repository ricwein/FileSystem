<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Helper;

use ricwein\FileSystem\Exceptions\ConstraintsException;

/**
 * file-path validation class
 */
class Constraint
{
    /**
     * no requirements
     * @var int
     */
    public const LOOSE = 0b00000000;

    /**
     * the resulting path is inside the first given path,
     * this mitigates /../ -traversion attacks
     * @var int
     */
    public const IN_SAFEPATH = 0b00000001;

    /**
     * checks if file is in open_basedir restrictions
     * @var int
     */
    public const IN_OPENBASEDIR = 0b00000010;

    /**
     * path must not be a symlink
     * @var int
     */
    public const DISALLOW_LINK = 0b00000100;

    /**
     * includes all Constraints
     * @var int
     */
    public const STRICT = 0b11111111;

    /**
     * @var string[]
     */
    protected const ERROR_MAP = [
        self::IN_SAFEPATH => 'the given path is not within the safepath',
        self::IN_OPENBASEDIR => 'the given path is not within the allowed \'open_basedir\' paths',
        self::DISALLOW_LINK => 'the given path contains a symlink',
    ];

    /**
     * @var int
     */
    protected $constraints;

    /**
     * @var int
     */
    protected $failedFor = 0;

    /**
     * @param int $constraints
     */
    public function __construct(int $constraints = self::STRICT)
    {
        $this->constraints = $constraints;
    }

    /**
     * @return int
     */
    public function getConstraints(): int
    {
        return $this->constraints;
    }

    /**
     * @param \Throwable|null $previous
     * @return ConstraintsException|null
     */
    public function getErrors(\Throwable $previous = null): ?ConstraintsException
    {
        foreach ([
            self::DISALLOW_LINK,
            self::IN_OPENBASEDIR,
            self::IN_SAFEPATH
        ] as $constraint) {

            // unsatisfied constraint detected
            if (($this->failedFor & $constraint) === $constraint) {
                $previous = new ConstraintsException('[' . $constraint . '] - constraint failed'. (isset(self::ERROR_MAP[$constraint]) ? (': ' . self::ERROR_MAP[$constraint]) : ''), 500, $previous);
            }
        }

        return $previous;
    }

    /**
     * @param Path $path
     * @return bool
     */
    public function isValidPath(Path $path): bool
    {
        // not in open_basedir restrictions
        if (
            ($this->constraints & self::IN_OPENBASEDIR) === self::IN_OPENBASEDIR
            && !$path->isInOpenBasedir()
        ) {
            $this->failedFor |= self::IN_OPENBASEDIR;
        }

        // path contains a symlink
        if (
            ($this->constraints & self::DISALLOW_LINK) === self::DISALLOW_LINK
            && file_exists($path->raw)
            && $path->fileInfo()->isLink()
        ) {
            $this->failedFor |= self::DISALLOW_LINK;
        }

        // ensure realpath is in original search path (prevent /../ cd's)
        if (
            ($this->constraints & self::IN_SAFEPATH) === self::IN_SAFEPATH
            && $path->raw !== $path->real
            && strpos($path->real, $path->safepath) !== 0
        ) {
            $this->failedFor |= self::IN_SAFEPATH;
        }

        return $this->failedFor === 0;
    }
}
