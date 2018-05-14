<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage\Disk;

use ricwein\FileSystem\Directory;
use ricwein\FileSystem\File;

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
     * @var string[]|Directory[]|File[]
     */
    private $components;

    /**
     * @var string|null
     */
    protected $filepath = null;

    /**
     * full resolved filesystem path
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
     * @var string|null
     */
    protected $directory = null;

    /**
     * file-extension of a file
     * e.g.: db
     * @var string|null
     */
    protected $extension = null;

    /**
     * @param string[]|Directory[]|File[] $components
     */
    public function __construct($components)
    {
        $this->components = $components;
    }

    /**
     * parse each path-component and extract path-info
    * @return string[]
    * @throws \UnexpectedValueException
     */
    protected function parseComponents(): array
    {
        $components = [];

        // parse path-components
        foreach ($this->components as $component) {
            if (is_string($component)) {
                $components[] = rtrim($component, '/\\' . DIRECTORY_SEPARATOR);
            } else {
                throw new \UnexpectedValueException(sprintf('invalid path-component of type \'%s\'', gettype($component)), 500);
            }
        }

        return $components;
    }

    /**
     * @return void
     * @throws \UnexpectedValueException
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
     * @param  string $key
     * @return string|null
     * @throws \UnexpectedValueException
     */
    public function __get(string $key): ?string
    {
        if (!property_exists($this, $key)) {
            throw new \UnexpectedValueException(sprintf('unknown path-component named \'%s\'', $key), 500);
        }

        if (!$this->loaded) {
            $this->resolvePath();
            $this->loaded = true;
        }

        return $this->$key;
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
}
