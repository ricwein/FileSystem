<?php

/**
 * @author Richard Weinhold
 */

declare(strict_types=1);

namespace ricwein\FileSystem\Directory;

use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Path;
use ricwein\FileSystem\Storage;

/**
 * represents a selected directory
 */
class Command extends Directory
{

    /**
     * full path to binary
     */
    protected ?string $bin = null;

    /**
     * default search paths for binaries
     * @var string[]
     */
    protected array $paths = [];

    protected int $lastExitCode = 0;
    protected ?string $lastCommand = null;

    /**
     * @inheritDoc
     * @param File[]|Storage\Disk[]|Path[]|string[] $executablePath
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws UnsupportedException
     */
    public function __construct(Storage\Disk $storage, int $constraints = Constraint::STRICT, array $executablePath = [])
    {
        parent::__construct($storage, $constraints);

        // try to find binary in given file-paths
        $this->bin = $this->selectBinaryPath([...$executablePath, ... $this->paths]);

        if ($this->bin === null) {
            throw new FileNotFoundException(sprintf('unable to find binary in paths: "%s"', implode('", "', $executablePath)), 500);
        }
    }

    /**
     * @param File[]|Storage\Disk[]|Path[]|string[] $paths
     * @throws ConstraintsException
     */
    protected function selectBinaryPath(array $paths): ?string
    {
        foreach ($paths as $path) {
            if ($path instanceof Path && $path->isFile() && $path->isExecutable()) {
                return (false !== $realpath = $path->getRealPath()) ? $realpath : null;
            }

            if ($path instanceof Storage\Disk && $path->isFile() && $path->isExecutable()) {
                return (false !== $realpath = $path->getPath()->getRealPath()) ? $realpath : null;
            }

            if ($path instanceof File && $path->isFile() && $path->storage()->isExecutable()) {
                return (false !== $realpath = $path->getPath()->getRealPath()) ? $realpath : null;
            }

            if (is_string($path) && file_exists($path) && is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    protected function bindVariables(string $cmd, object|array $variables): string
    {
        return preg_replace_callback('/\$\(\s*([^)]+)\s*\)/', static function ($match) use ($variables): string {
            $variable = explode('.', trim($match[1]));

            // traverse template variable
            $current = $variables;
            foreach ($variable as $value) {

                // match against current bindings tree
                $isObject = is_object($current);

                if ($isObject && method_exists($current, $value)) {
                    $current = $current->$value();
                } elseif ($isObject && (property_exists($current, $value) || isset($current->$value))) {
                    $current = $current->$value;
                } elseif (is_array($current) && array_key_exists($value, $current)) {
                    $current = $current[$value];
                } else {
                    return $match[0];
                }
            }

            // check for return type
            if ($current === null) {
                return '';
            }

            if (is_scalar($current)) {
                return (string)$current;
            }

            if (is_object($current) && method_exists($current, '__toString')) {
                return (string)$current;
            }

            return $match[0];
        }, $cmd);
    }


    /**
     * @throws AccessDeniedException
     * @throws FileNotFoundException
     * @throws RuntimeException
     */
    public function execSafe(string $cmd = '', array $arguments = []): bool|string
    {
        $cmd = trim(str_ireplace(['&', ';'], '', $cmd));
        $cmd = $this->bindVariables($cmd, $arguments);
        $cmd = escapeshellcmd($cmd);
        return $this->exec($cmd);
    }

    /**
     * @throws AccessDeniedException
     * @throws FileNotFoundException
     * @throws RuntimeException
     */
    public function exec(string $cmd = '', array $arguments = [], bool $prependBinary = true): bool|string
    {

        // validate constraints
        if (!$this->isDir() || !$this->storage->doesSatisfyConstraints()) {
            throw new FileNotFoundException('unable to open directory', 404, $this->storage->getConstraintViolations());
        }

        $result = [];
        $this->lastExitCode = 0;

        // cleanup cmd
        if ($prependBinary) {
            $cmd = trim(str_ireplace("$this->bin ", '', $cmd));
            $cmd = sprintf("\"%s\" %s", $this->bin, $cmd);
        }

        $cmd = $this->bindVariables($cmd, $arguments);
        $cmd = $this->bindVariables($cmd, ['path' => $this->storage->getPath()]);
        $cmd = $this->bindVariables($cmd, ['bin' => $this->bin]);

        $cmd = rtrim($cmd);
        $this->lastCommand = $cmd;

        if (!$this->storage instanceof Storage\Disk) {
            throw new RuntimeException(sprintf('unsupported storage system for Command-Execution: %s', $this->storage::class), 500);
        }

        $path = $this->storage->getPath()->getRealPath();

        if (!chdir($path)) {
            throw new AccessDeniedException('changing directory failed', 500);
        }

        // run command (safe)
        if (function_exists('exec')) {
            exec($cmd . ' 2>&1', $result, $this->lastExitCode);
            if ($this->lastExitCode !== 0) {
                throw new RuntimeException('shell execution failed: ' . (count($result) < 3 ? implode(' ', $result) : reset($result)), 500);
            }
            $result = implode(PHP_EOL, array_map('trim', $result));
            return empty($result) ? true : trim($result);
        }

        if (function_exists('shell_exec')) {
            $result = shell_exec($cmd . ' 2>&1');
            if ($result === null || $result === false) {
                return false;
            }

            $result = explode(PHP_EOL, trim($result));
            $result = implode(PHP_EOL, array_map('trim', $result));
            return empty($result) ? true : trim($result);
        }

        throw new RuntimeException('shell-execution is disabled', 500);
    }

    public function getLastCommand(): ?string
    {
        return $this->lastCommand;
    }

    public function lastExitCode(): int
    {
        return $this->lastExitCode;
    }

    public function __toString(): string
    {
        return sprintf('%s binary: "%s"', parent::__toString(), $this->bin);
    }
}
