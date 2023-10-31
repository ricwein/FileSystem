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
    public function getConstraints(): ?int
    {
        return $this->constraints?->getConstraints();
    }

    /**
     * @internal
     */
    public function getConstraintViolations(ConstraintsException $previous = null): ?ConstraintsException
    {
        return $this->constraints?->getErrors($previous);
    }

    /**
     * check if current path satisfies the given constraints
     * @internal
     */
    abstract public function doesSatisfyConstraints(): bool;

    public function __toString(): string
    {
        $namespace = explode('\\', static::class);
        return sprintf('[Storage: %s]', end($namespace));
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

    /**
     * @return array{p: null|array, c: null|Constraint}
     */
    public function __serialize(): array
    {
        return [
            'p' => ($this->path ?? null)?->_getPathComponents(),
            'c' => $this->constraints,
        ];
    }

    /**
     * @param array{p: null|array, c: null|Constraint} $data
     */
    public function __unserialize(array $data): void
    {
        if (!empty($data['p'])) {
            $this->path = new Path(...$data['p']);
        }
        $this->constraints = $data['c'] ?? null;
    }
}
