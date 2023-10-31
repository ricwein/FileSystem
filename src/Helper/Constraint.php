<?php
declare(strict_types=1);

namespace ricwein\FileSystem\Helper;

use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Path;

/**
 * file-path validation class
 */
final class Constraint
{
    /**
     * no requirements
     */
    public const LOOSE = 0b00000000;

    /**
     * the resulting path is inside the first given path,
     * this mitigates /../ -traversal attacks
     */
    public const IN_SAFEPATH = 0b00000001;

    /**
     * checks if file is in open_basedir restrictions
     */
    public const IN_OPEN_BASEDIR = 0b00000010;

    /**
     * path must not be a symlink
     */
    public const DISALLOW_LINK = 0b00000100;

    /**
     * includes all Constraints
     */
    public const STRICT = 0b11111111;

    protected array $errors = [];
    protected int $constraints;
    protected int $failedFor = 0;
    protected bool $hasRun = false;

    public function __construct(int $constraints = self::STRICT)
    {
        $this->constraints = $constraints;
    }

    public function getConstraints(): int
    {
        return $this->constraints;
    }

    public function getErrors(ConstraintsException $previous = null): ?ConstraintsException
    {
        $rules = [
            self::DISALLOW_LINK,
            self::IN_OPEN_BASEDIR,
            self::IN_SAFEPATH,
        ];

        foreach ($rules as $constraint) {
            // iterative exception chaining:
            if (($this->failedFor & $constraint) === $constraint) {
                $previous = new ConstraintsException('[' . $constraint . '] - constraint failed' . (isset($this->errors[$constraint]) ? (': ' . $this->errors[$constraint]) : ''), 500, $previous);
            }
        }

        return $previous;
    }

    public function isValidPath(Path $path): bool
    {
        if ($this->hasRun) {
            return $this->failedFor === 0;
        }

        // not in open_basedir restrictions
        if (
            ($this->constraints & self::IN_OPEN_BASEDIR) === self::IN_OPEN_BASEDIR
            && !$path->isInOpenBasedir()
        ) {
            $this->failedFor |= self::IN_OPEN_BASEDIR;
            $this->errors[self::IN_OPEN_BASEDIR] = sprintf('the given path (%s) is not within the allowed \'open_basedir\' paths', $path->getRealOrRawPath());
        }

        // path contains a symlink
        if (
            ($this->constraints & self::DISALLOW_LINK) === self::DISALLOW_LINK
            && $path->doesExist()
            && $path->isLink()
        ) {
            $this->failedFor |= self::DISALLOW_LINK;
            $this->errors[self::DISALLOW_LINK] = sprintf('the given path (%s) contains a symlink', $path->getRealOrRawPath());
        }

        // ensure realpath is in original search path (prevent /../ cd's)
        if (($this->constraints & self::IN_SAFEPATH) === self::IN_SAFEPATH && !$path->isInSafePath()) {
            $this->failedFor |= self::IN_SAFEPATH;
            $this->errors[self::IN_SAFEPATH] = sprintf('the given path (%s) is not within the safepath (%s)', $path->getRealOrRawPath(), $path->getRealOrRawPath());
        }

        $this->hasRun = true;
        return $this->failedFor === 0;
    }

    public function __serialize(): array
    {
        return [$this->constraints];
    }

    public function __unserialize(array $data): void
    {
        [$this->constraints] = $data;
    }
}
