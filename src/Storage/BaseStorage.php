<?php
declare(strict_types=1);

namespace ricwein\FileSystem\Storage;

use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Path;
use Stringable;

/**
 * base-implementation for all Storage Adapters
 */
abstract class BaseStorage implements StorageInterface, Stringable
{
    protected ?Constraint $constraints = null;
    protected Path $path;

    /**
     * remove file from filesystem on object destruction
     * => leaving scope or removing object reference
     */
    protected bool $selfDestruct = false;

    /**
     * {@inheritDoc}
     */
    public function getDetails(): array
    {
        return ['storage' => static::class];
    }

    /**
     * @internal
     */
    public function setConstraints(int $constraints): static
    {
        $this->constraints = new Constraint($constraints);
        return $this;
    }

    /**
     * @internal
     */
    public function getConstraints(): int
    {
        return $this->constraints->getConstraints();
    }

    /**
     * @internal
     */
    public function getConstraintViolations(ConstraintsException $previous = null): ?ConstraintsException
    {
        return $this->constraints->getErrors($previous);
    }

    /**
     * check if current path satisfies the given constraints
     * @internal
     */
    abstract public function doesSatisfyConstraints(): bool;

    public function __toString(): string
    {
        return sprintf('[Storage: %s]', trim(str_replace(self::class, '', get_class($this)), '\\'));
    }

    /**
     * {@inheritDoc}
     */
    public function getPath(): Path
    {
        return $this->path;
    }

    /**
     * {@inheritDoc}
     */
    public function removeOnFree(bool $activate = true): static
    {
        $this->selfDestruct = $activate;
        return $this;
    }
}
