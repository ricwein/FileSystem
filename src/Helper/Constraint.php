<?php

/**
 * @author Richard Weinhold
 */

namespace ricwein\FileSystem\Helper;

use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use Throwable;

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
    protected $errors = [];

    /**
     * @var int
     */
    protected $constraints;

    /**
     * @var int
     */
    protected $failedFor = 0;

    /**
     * @var bool
     */
    protected $hasRun = false;

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
     * @param Throwable|null $previous
     * @return Throwable|ConstraintsException|null
     */
    public function getErrors(Throwable $previous = null): ?Throwable
    {
        foreach ([
                     self::DISALLOW_LINK,
                     self::IN_OPENBASEDIR,
                     self::IN_SAFEPATH
                 ] as $constraint) {

            // iterative exception chaining:
            if (($this->failedFor & $constraint) === $constraint) {
                $previous = new ConstraintsException('[' . $constraint . '] - constraint failed' . (isset($this->errors[$constraint]) ? (': ' . $this->errors[$constraint]) : ''), 500, $previous);
            }
        }

        return $previous;
    }

    /**
     * @param Path $path
     * @return bool
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function isValidPath(Path $path): bool
    {
        if ($this->hasRun) {
            return $this->failedFor === 0;
        }

        // not in open_basedir restrictions
        if (
            ($this->constraints & self::IN_OPENBASEDIR) === self::IN_OPENBASEDIR
            && !$path->isInOpenBasedir()
        ) {
            $this->failedFor |= self::IN_OPENBASEDIR;
            $this->errors[self::IN_OPENBASEDIR] = sprintf('the given path (%s) is not within the allowed \'open_basedir\' paths', $path->raw);
        }

        // path contains a symlink
        if (
            ($this->constraints & self::DISALLOW_LINK) === self::DISALLOW_LINK
            && file_exists($path->raw)
            && $path->fileInfo()->isLink()
        ) {
            $this->failedFor |= self::DISALLOW_LINK;
            $this->errors[self::DISALLOW_LINK] = sprintf('the given path (%s) contains a symlink', $path->raw);
        }

        // ensure realpath is in original search path (prevent /../ cd's)
        if (
            ($this->constraints & self::IN_SAFEPATH) === self::IN_SAFEPATH
            && (
                (file_exists($path->raw) && $path->raw !== $path->real && strpos($path->real, $path->safepath) !== 0)
                || (!file_exists($path->raw) && strpos($path->raw, $path->safepath) !== 0))
        ) {
            $this->failedFor |= self::IN_SAFEPATH;
            $this->errors[self::IN_SAFEPATH] = sprintf('the given path (%s) is not within the safepath (%s)', $path->raw, $path->safepath);
        }

        $this->hasRun = true;
        return $this->failedFor === 0;
    }
}
