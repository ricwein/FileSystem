<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Helper;

use ricwein\FileSystem\Storage;
use ricwein\FileSystem\FileSystem;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;

/**
 * path-resolver for filesystem
 */
class Path
{

    /**
     * @var bool
     */
    private $loaded = false;

    /**
     * @var string[]|FileSystem[]|self[]
     */
    private $components;

    /**
     * relative path to file
     * e.g.: test/test.db
     * @var string|null
     */
    protected $filepath = null;

    /**
     * full resolved filesystem path
     * e.g.: /res/test/test.db
     * @var string|null
     */
    protected $real = null;

    /**
     * full but raw path (can contain unresolved /../ parts!)
     * e.g.: /res/var/../test/test.db
     * @var string
     */
    protected $raw;

    /**
     * part of the path which can be assumed to be save (can contain unresolved /../ parts!)
     * this path should not be left (protected against /../ -directory traversion)
     * e.g.: /res/
     * @var string
     */
    protected $safepath;

    /**
     * name of a file
     * e.g.: test.db
     * @var string|null
     */
    protected $filename = null;

    /**
    * name of a directory or file without file-extension
    * e.g.: test
    * @var string
     */
    protected $basename;

    /**
     * path of directory (can contain unresolved /../ parts!)
     * e.g.: res/test/
     * @var string
     */
    protected $directory;

    /**
     * @var string
     */
    protected $pathname;

    /**
     * file-extension of a file
     * e.g.: db
     * @var string|null
     */
    protected $extension = null;

    /**
     * @var \SplFileInfo|null
     */
    protected $fileInfo = null;

    /**
     * @param string[]|FileSystem[]|self[] $components
     * @throws UnexpectedValueException
     */
    public function __construct(array $components)
    {
        if (empty($components)) {
            throw new UnexpectedValueException('unable to initialize a disk-path without path-components', 500);
        }
        $this->components = $components;
    }

    /**
     * parse each path-component and extract path-info
    * @return string[]
    * @throws UnexpectedValueException|RuntimeException
     */
    protected function normalizePathComponents(): array
    {
        $components = [];

        // fetch key of first and last item
        $keys = array_keys($this->components);
        $first = reset($keys);
        $last = end($keys);

        // iterate through all path-components
        foreach ($this->components as $key => $component) {

            /**
             * prepare resolve-path string
             * @var string
             */
            $path = '';

            // parse path-component
            if (is_string($component)) {
                $path = $component;
            } elseif ($component instanceof self || $component instanceof FileSystem || $component instanceof Storage\Disk) {

                /** @var Path $pathObj */
                $pathObj = $component instanceof self ? $component : $component->path();

                switch ($key) {
                    case $last: $path = $pathObj->raw; break; // last part
                    case $first: $path = ($pathObj->fileInfo()->isDir() ? $pathObj->raw : $pathObj->directory); break; // first part
                    default: $path = $pathObj->directory; break; // middle parts
                }
            } else {
                throw new UnexpectedValueException(sprintf('invalid path-component of type \'%s\'', is_object($component) ? get_class($component) : gettype($component)), 500);
            }

            // normalize path
            $path = str_replace(['/', '\\', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR], DIRECTORY_SEPARATOR, $path);

            if ($key === $first && $path === DIRECTORY_SEPARATOR) {
                $components[] = DIRECTORY_SEPARATOR;
            } elseif ($key === $first) {
                $components[] = rtrim($path, DIRECTORY_SEPARATOR);
            } else {
                $components[] = trim($path, DIRECTORY_SEPARATOR);
            }
        }

        return $components;
    }

    /**
     * @return void
     * @throws UnexpectedValueException
     */
    protected function resolvePath(): void
    {
        if (count($this->components) === 1 && reset($this->components) instanceof self) {

            /** @var Path $path */
            $path = current($this->components);

            $this->safepath = $path->safepath;
            $this->raw = $path->raw;
            $this->filepath = $path->filepath;
            $this->directory = $path->directory;
            $this->real = $path->real;
            $this->basename = $path->basename;
            $this->extension = $path->extension;
            $this->filename = $path->filename;

            $this->fileInfo = new \SplFileInfo($this->raw);
            $this->loaded = true;
            return;
        }

        $components = $this->normalizePathComponents();

        // save and cleanup the inital path component,
        // which we can asume is safe for all coming path-components
        $safepath = reset($components);
        if (is_dir($safepath)) {
            $safepath = realpath($safepath);
        } elseif (is_file($safepath)) {
            $safepath = dirname(realpath($safepath));
        }
        $this->safepath = $safepath;

        // cleanup path variable, remove duplicated DS
        $path = implode(DIRECTORY_SEPARATOR, $components);
        $path = str_replace(DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $path);

        $this->filepath = str_replace($this->safepath, '', $path);

        $this->raw = $path;
        $this->fileInfo = new \SplFileInfo($path);

        // parse into path-details
        $this->directory = $this->fileInfo->getPath();
        $this->real = $this->fileInfo->getRealPath();
        $this->pathname = $this->fileInfo->getPathname();

        if ($this->fileInfo->isFile()) {
            $this->extension = $this->fileInfo->getExtension();
            $this->filename = $this->fileInfo->getFilename();
            $this->basename = $this->fileInfo->getBasename('.' . $this->extension);
        } else {
            $this->extension = null;
            $this->filename = null;
            $this->basename = $this->fileInfo->getBasename();
        }

        $this->loaded = true;
    }

    /**
     * reset internal loaded-state,
     * resulting in reloading all paths on next access
     * @return self
     */
    public function reload(): self
    {

        // we don't need to reload if the path isn't loaded in the first place
        if ($this->loaded === false) {
            return $this;
        }

        // reset path- and fileInfo-cache
        clearstatcache(false, $this->raw);

        $this->loaded = false;

        return $this;
    }

    /**
    * @return \SplFileInfo
    */
    public function fileInfo(): \SplFileInfo
    {
        if (!$this->loaded) {
            $this->resolvePath();
        }

        return $this->fileInfo;
    }


    /**
     * check if path is in open_basedir restrictions
     * @return bool
     */
    public function isInOpenBasedir() : bool
    {
        if (!$this->loaded) {
            $this->resolvePath();
        }

        /**
         * fetch openBaseDir
         * @var string[]
         */
        static $openBaseDirs = null;

        if ($openBaseDirs === null) {
            $openBaseDirs = array_filter(explode(':', trim(ini_get('open_basedir'))), function ($dir): bool {
                return !empty($dir);
            });
        }

        // no basedir specified, therefor assume system is local only
        if (empty($openBaseDirs)) {
            return true;
        }

        // check against open_basedir paths
        foreach ((array) $openBaseDirs as $dir) {
            $dir = realpath($dir);
            if (stripos($this->raw, $dir) === 0) {
                return true;
            }
        }

        return false;
    }


    /**
     * returns all path-properties for testing/debugging purposes
     * @return string[]
     */
    public function getDetails(): array
    {
        if (!$this->loaded) {
            $this->resolvePath();
        }

        $path = [
            'rawpath' => $this->raw,
            'realpath' => $this->real,
            'directory' => $this->directory,

            'safepath' => $this->safepath,
            'filepath' => $this->filepath,

            'basename' => $this->basename,
            'filename' => $this->filename,
            'extension' => $this->extension,
        ];

        $splInfo = !is_file($this->raw) ? false : [
            'type' => $this->fileInfo->getType(),
            'size' => $this->fileInfo->getSize(),
        ];

        return [
            'path' => $path,
            'splInfo' => $splInfo,
        ];
    }

    /**
     * @param  string $key
     * @return string|null
     * @throws UnexpectedValueException
     */
    public function __get(string $key): ?string
    {
        if (!$this->loaded) {
            $this->resolvePath();
        }

        if (!property_exists($this, $key)) {
            throw new UnexpectedValueException(sprintf('unknown path-component named \'%s\'', $key), 500);
        }

        return $this->$key;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        if (!$this->loaded) {
            $this->resolvePath();
        }

        return $this->raw;
    }
}
