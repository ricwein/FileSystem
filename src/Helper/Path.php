<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Helper;

use ricwein\FileSystem\FileSystem;
use ricwein\FileSystem\Exception\RuntimeException;
use ricwein\FileSystem\Exception\UnexpectedValueException;

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
     * full but raw path
     * e.g.: /res/var/../test/test.db
     * @var string
     */
    protected $raw;

    /**
     * part of the path which can be assumed to be save
     * this path should not be left (protected against /../ -directory traversion)
     * e.g.: /res/
     * @var string
     */
    protected $savepath;

    /**
     * name of a file without file-extension
     * e.g.: test
     * @var string|null
     */
    protected $filename = null;

    /**
    * name of a file
    * e.g.: test.db
    * @var string|null
     */
    protected $basename = null;

    /**
     * path of directory
     * e.g.: res/test/
     * @var string
     */
    protected $directory;

    /**
     * file-extension of a file
     * e.g.: db
     * @var string|null
     */
    protected $extension = null;

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
    protected function parseComponents(): array
    {
        $components = [];

        // fetch key of first item
        reset($this->components);
        $first = key($this->components);

        // iterate through all path-components
        foreach ($this->components as $key => $component) {

            /**
             * prepare resolve path string
             * @var string
             */
            $path = '';

            // parse path-component
            if (is_string($component)) {
                $path = $component;
            } elseif ($component instanceof self) {
                $path = (next($this->components) === false) ? $component->raw : $component->directory;
            } elseif ($component instanceof FileSystem) {
                $path = (next($this->components) === false) ? $component->path()->raw : $component->path()->directory;
            } else {
                throw new UnexpectedValueException(sprintf('invalid path-component of type \'%s\'', gettype($component)), 500);
            }

            if ($first === $key) {
                $components[] = rtrim($path, '/\\' . DIRECTORY_SEPARATOR);
            } else {
                $components[] = trim($path, '/\\' . DIRECTORY_SEPARATOR);
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
        $components = $this->parseComponents();

        $this->savepath = reset($components);

        // cleanup path variable
        $path = implode(DIRECTORY_SEPARATOR, $components);
        $path = str_replace(['/', '\\', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR], DIRECTORY_SEPARATOR, $path);

        $this->filepath = str_replace($this->savepath, '', $path);

        // parse into path-details
        $details = pathinfo($path, PATHINFO_DIRNAME | PATHINFO_BASENAME | PATHINFO_EXTENSION | PATHINFO_FILENAME);
        $this->extension = $details['extension'];
        $this->filename = $details['filename'];
        $this->directory = $details['dirname'];
        $this->basename = $details['basename'];

        $this->raw = $path;

        if (false !== $realpath = realpath($path)) {
            $this->real = $realpath;
        }

        $this->loaded = true;
    }

    /**
     * reset internal loaded-state,
     * resulting in reloading all paths on next access
     * @return self
     */
    public function reload():self
    {
        $this->loaded = false;
        return $this;
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
            $openBaseDirs = explode(':', trim(ini_get('open_basedir')));
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

        return [
            'rawpath' => $this->raw,
            'realpath' => $this->real,
            'directory' => $this->directory,

            'savepath' => $this->savepath,
            'filepath' => $this->filepath,

            'basename' => $this->basename,
            'filename' => $this->filename,
            'extension' => $this->extension,
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
}
