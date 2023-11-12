<?php

declare(strict_types=1);

namespace ricwein\FileSystem;

use DateTime;
use DateTimeInterface;
use SplFileInfo;

/**
 * @author Richard Weinhold
 */
final class Path extends SplFileInfo
{
    private readonly string $scheme;

    private readonly string $safePath;
    private readonly ?string $safePathReal;

    private readonly array $pathComponents;

    public function __construct(FileSystem|Path|int|string|Storage\Disk|SplFileInfo ...$path)
    {
        // fetch key of first and last item
        $positionStart = array_key_first($path);
        $positionEnd = array_key_last($path);

        // iterate through all path-components
        $components = [];
        $safepath = null;
        foreach ($path as $position => $component) {

            if ($position === $positionStart && $component instanceof self) {
                $safepath = $component->getSafePath();
            }

            // parse single path-component
            $normalizedPath = self::normalize($component, $position === $positionStart, $position === $positionEnd);
            $normalizedPath = str_replace(['/', DIRECTORY_SEPARATOR, '\\', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR], DIRECTORY_SEPARATOR, $normalizedPath);

            if ($position === $positionStart) {
                $components[] = ($normalizedPath === DIRECTORY_SEPARATOR) ? DIRECTORY_SEPARATOR : rtrim($normalizedPath, DIRECTORY_SEPARATOR);
            } else {
                $components[] = trim($normalizedPath, DIRECTORY_SEPARATOR);
            }
        }

        $this->pathComponents = $components;
        $safepath ??= reset($components);

        // use containing directory as safepath for files
        if (is_file($safepath) && !is_dir($safepath)) {
            $safepath = dirname($safepath);
        }

        $resultPath = implode(
            DIRECTORY_SEPARATOR,
            array_map(
                static fn(string $p) => rtrim($p, DIRECTORY_SEPARATOR),
                $components
            )
        );

        if (
            $resultPath === ''
            && str_contains(implode(DIRECTORY_SEPARATOR, $components), DIRECTORY_SEPARATOR)
        ) {
            $resultPath = DIRECTORY_SEPARATOR;
        }

        $parts = parse_url($resultPath);
        if (isset($parts['scheme'])) {
            $this->scheme = $parts['scheme'];
        } else {
            $this->scheme = 'file';
        }

        $this->safePath = $safepath;
        $this->safePathReal = realpath($safepath) ?: null;

        parent::__construct($parts['path'] ?? $resultPath);
    }

    private static function normalize(FileSystem|Path|int|string|Storage\Disk|SplFileInfo $component, bool $isFirstElement, bool $isLastElement): string
    {
        if ($component instanceof FileSystem || $component instanceof Storage\Disk) {
            return self::normalize($component->getPath(), $isFirstElement, $isLastElement);
        }

        if ($component instanceof self) {
            if ($isLastElement) {
                return $component->getPathname(); // last part
            }
            if ($isFirstElement) {
                return match (true) {
                    $component->isDir() => $component->getPathname(),
                    $component->isFile() => $component->getDirectory(),
                    default => $component->getPathname(),
                };
            }
            return $component->getDirectory(); // middle parts
        }

        if ($component instanceof SplFileInfo) {
            if ($isLastElement) {
                return $component->getPathname(); // last part
            }
            if ($isFirstElement) {
                return ($component->isDir() ? $component->getPathname() : dirname($component->getPathname())); // first part
            }
            return dirname($component->getPathname()); // middle parts
        }

        return (string)$component;
    }

    public function getBasename(?string $suffix = null): string
    {
        if ($suffix === null && !$this->isDir()) {
            $suffix = ".{$this->getExtension()}";
        }

        return $suffix !== null ? parent::getBasename($suffix) : parent::getBasename();
    }

    public function getSafePath(): string
    {
        return $this->safePath;
    }

    public function getSafePathReal(): ?string
    {
        return $this->safePathReal;
    }

    public function getRelativePath(self|string $path): string
    {
        $rootPath = match (true) {
            is_string($path) => realpath($path) ?: $path,
            $path->isFile() => $path->getDirectory(),
            default => $path->getRealOrRawPath(),
        };
        $rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $filepath = $this->getRealOrRawPath();

        if (str_starts_with($filepath, $rootPath)) {
            $relativePath = ltrim(substr($filepath, strlen($rootPath)), DIRECTORY_SEPARATOR);
        } else {
            return $filepath;
        }

        if ($this->isDir()) {
            $relativePath = rtrim($relativePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }

        return ($relativePath === DIRECTORY_SEPARATOR) ? '' : $relativePath;
    }

    public function getRelativePathToSafePath(): string
    {
        return $this->getRelativePath($this->getSafePathReal());
    }

    public function isInSafePath(null|self|string $path = null): bool
    {
        $path = match (true) {
            is_string($path) => new self($path),
            is_null($path) => $this,
            default => $path,
        };

        if ((false !== $realpath = $path->getRealPath()) && (false !== $safePath = realpath($this->getSafePath()))) {
            return str_starts_with($realpath, $safePath);
        }

        return str_starts_with($path->getPathname(), $this->getSafePath());
    }

    public function isInPath(string|self $path): bool
    {
        if (!$path instanceof self) {
            $path = new self($path);
        }

        if ((false !== $realpath = $this->getRealPath()) && (false !== $searchRealpath = $path->getRealPath())) {
            return str_starts_with($realpath, $searchRealpath);
        }

        return str_starts_with($this->getPathname(), $path->getPathname());
    }

    public function isInOpenBaseDir(): bool
    {
        static $openBaseDirs = null;
        if ($openBaseDirs === null) {
            $openBaseDirs = array_filter(explode(':', trim(ini_get('open_basedir'))), static fn($dir): bool => !empty($dir));
        }

        // no basedir specified, therefore assume system is local only
        if (empty($openBaseDirs)) {
            return true;
        }

        foreach ($openBaseDirs as $openBaseDir) {
            if ($this->isInPath($openBaseDir)) {
                return true;
            }
        }
        return false;
    }

    public function getDirectory(): string
    {
        if (false !== $realPath = $this->getRealPath()) {
            return dirname($realPath);
        }

        return $this->getPath();
    }

    public function getEscapedFilename(): string
    {
        $filename = strip_tags($this->getFilename());

        $filename = preg_replace('/[\r\n\t\s]+/', ' ', $filename);
        $filename = preg_replace('/[\"*\/:<>?\'|]+/', ' ', $filename);
        $filename = html_entity_decode($filename, ENT_QUOTES, "utf-8");
        $filename = htmlentities($filename, ENT_QUOTES, "utf-8");

        $filename = strtr($filename, [
            'Ü' => 'Ue',
            'Ü' => 'Ue',
            'Ö' => 'Oe',
            'Ä' => 'Ae',
            'ü' => 'ue',
            'ö' => 'oe',
            'a' => 'ae',
            'ß' => 'ss',
            ' ' => '_'
        ]);

        if (function_exists('transliterator_transliterate')) {
            $filename = transliterator_transliterate('Any-Latin; Latin-ASCII;', $filename);
        }

        $filename = preg_replace("/(&)([a-z])([a-z]+;)/i", '$2', $filename);
        $filename = preg_replace('/\s+/', '-', $filename);
        $filename = rawurlencode($filename);
        return str_replace('%', '-', $filename);
    }

    public function getRawPath(): string
    {
        return $this->getPathname();
    }

    public function doesExist(): bool
    {
        return $this->isFile() || $this->isDir();
    }

    public function isDotfile(): bool
    {
        return str_starts_with($this->getFilename(), '.');
    }

    public function getMDate(): ?DateTimeInterface
    {
        if (!$this->doesExist() || (false === $mtime = $this->getMTime())) {
            return null;
        }
        return DateTime::createFromFormat('U', (string)$mtime);
    }

    public function getADate(): ?DateTimeInterface
    {
        if (!$this->doesExist() || (false === $atime = $this->getATime())) {
            return null;
        }
        return DateTime::createFromFormat('U', (string)$atime);
    }

    public function getCDate(): ?DateTimeInterface
    {
        if (!$this->doesExist() || (false === $ctime = $this->getCTime())) {
            return null;
        }
        return DateTime::createFromFormat('U', (string)$ctime);
    }

    public function getUrl(): string
    {
        return sprintf("%s://%s", $this->scheme, $this->getRealOrRawPath());
    }

    /**
     * @internal
     */
    public function reload(): self
    {
        clearstatcache(false, $this->getPathname());
        return $this;
    }

    /**
     * Get either the realpath if available, or the rawpath as a fallback.
     */
    public function getRealOrRawPath(): string
    {
        if (false !== $realPath = $this->getRealPath()) {
            return $realPath;
        }
        return $this->getPathname();
    }

    public function __toString(): string
    {
        return $this->getRealOrRawPath();
    }

    /**
     * @internal
     */
    public function __debugInfo(): array
    {
        return [
            'scheme' => $this->scheme,
            'rawPath' => $this->getPathname(),
            'realPath' => $this->getRealPath(),
            'safePath' => $this->getSafePath(),
            'filename' => $this->getFilename(),
            'basename' => $this->getBasename(),
            'extension' => $this->getExtension(),
            'size' => $this->doesExist() ? $this->getSize() : null,
            'isInSafePath' => $this->isInSafePath(),
            'isInOpenBaseDir' => $this->isInOpenBaseDir(),
            'isDir' => $this->isDir(),
            'isFile' => $this->isFile(),
            'mTime' => $this->getMDate()?->format('Y-m-d H:i:s'),
            'aTime' => $this->getADate()?->format('Y-m-d H:i:s'),
            'cTime' => $this->getCDate()?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return string[]
     * @internal
     */
    public function _getPathComponents(): array
    {
        return $this->pathComponents;
    }
}
