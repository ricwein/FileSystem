# FileSystem

This Library provides an Filesystem abstraction layer.

```php
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Exceptions\FileNotFoundException;

try {

    $file = new File(new Storage\Disk(__DIR__, '/test', '/dir2', 'test.json'));
    $alt = new File(new Storage\Memory(json_encode(['some' => 'content'])));

    // read file into output-buffer
    if ($file->isReadable()) {
        header('Content-Type: ' . $file->getType(true));
        echo $file->read();
    } else {
        header('Content-Type: ' . $alt->getType(true));
        echo $alt->read();
    }


} catch (FileNotFoundException $e) {

    // file was not found
    http_response_code(404);
    echo json_encode(['errors' => [
        'status' => 404,
        'title' => 'file not found',
        'detail' => $e->getMessage(),
    ]]);

} catch (\Exception $e) {

    // something else went wrong
    http_response_code(500);
    echo json_encode(['errors' => [
        'status' => $e->getCode(),
        'title' => 'something went wrong',
        'detail' => $e->getMessage(),
    ]]);
}
```

## Installation

```shell
composer require ricwein/filesystem
```

## Overview

Let's begin with a short overview over the supported Filesystem classes and abstractions.

All classes uses the root-namespace `ricwein\FileSystem`.

### Object-Types

A File is represented as a `File` Object and a Directory as a `Directory` Object.

### Storage

Accessing the Objects (File/Directory) Content is abstracted as `Storage`s. A File can be either:

- a `Disk` file at your local filesystem (`Storage\Disk`)
- a `Memory` file, which only temporary exists in-memory (`Storage\Memory`)
- an abstract `Flysystem` file (`Storage\Flysystem`)

All Storage-Types must extend the abstract base class `Filesystem\Storage`.

### Exceptions

Accessing File/Directory Attributes can result in throwing Exceptions. All Exceptions extend `Exceptions\Exception`.

## Usage: Files

All *FileSystem*-base-classes must be initialized using a Storage.

### Methods

| method | description |
|---|---|
| `read(?$offset, ?$length, $mode)` | read and return file-content, allows partial read with `$offset` and `$length` parameters, file is locked while reading with `$mode`|
| `write($content, $append, $mode)` | write `$content` to file, creates new file if it doesn't already exists, allows appended writing if `$append` isset, locks file with `$mode` |
| `copyTo($destination, $constraints)` | copy file to new `$destination` Storage-adapter |
| `moveTo($destination, $constraints)` | like `copyTo()`, but moves files instead |
| `touch($ifNewOnly)` | create file if it doesn't exists, updates last-modified timestamp |
| `remove()` | try to remove file |
| `getType($withEncoding)` | guess files mime-type |
| `getTime()` | get last-modified unix-timestamp |
| `getSize()` | calculate size |
| `getHash($mode, $algo)` | calculates a hash over `$mode` with `$algo` algorithm |
| `isReadable()` | is file readable? |
| `isWriteable()` | is file writeable? |
| `isSymlink()` | is file a symlink? |
| `isFile()` | is selected path an actual file? |
| `isDir()` | is selected path a directory? => always `false` for File instance |
| `isDotfile()` | is file a hidden dot-file? |
| `isValid()` | run constraints validation |
| `binary()` | gets new file-Handle for binary file-access |
| `storage()` | access internal storage adapter |
| `path()` | tries to access filesytem-path |

### Open and read a file from the local filesystem

```php
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;

$file = new File(new Storage\Disk(__DIR__, 'test.txt'));
$content = $file->read();
```

### or from in-memory-file

```php
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;

$file = new File(new Storage\Memory('some content'));
$content = $file->read();
```

### or from a Flysystem object

```php
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use League\Flysystem\Adapter\Local;

$file = new File(new Storage\Flysystem(new Local(__DIR__), 'test.txt'));
$content = $file->read();
```

## Usage: Directories

Like Files, Directories must be initialized using a Storage.

### Methods

| method | description |
|---|---|
| `list($recursive, $constraints)` | lists directory-content as new `File`/`Directory` objects |
| `listFiles($recursive, $constraints)` | like `list()` but ignores directories |
| `listDirs($recursive, $constraints)` | like `list()` but ignores files |
| `mkdir()` | try to create directory |
| `remove()` | try to remove directory |
| `getTime()` | get last-modified unix-timestamp |
| `getSize()` | calculate size |
| `getHash($mode, $algo)` | calculates a hash over `$mode` with `$algo` algorithm |
| `isReadable()` | is directory readable? |
| `isWriteable()` | is directory writeable? |
| `isSymlink()` | is directory a symlink? |
| `isFile()` | is selected path a file? => always `false` for Directory instance |
| `isDir()` | is selected path an actual directory? |
| `isDotfile()` | is directory a hidden dot-file? |
| `isValid()` | run constraints validation |
| `storage()` | access internal storage adapter |
| `path()` | tries to access filesytem-path |

### check if directory is readable

```php
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Storage;

$dir = new Directory(new Storage\Disk(__DIR__));
var_dump($dir->isReadable());
```

### list all files in directory

```php
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Storage;

$hashes = [];
$dir = new Directory(new Storage\Disk(__DIR__));

foreach($dir->listFiles(true) as $file) {
    $hashes[$file->path()->basename] = $file->getHash();
}
```

## Security

Using this filesytem layer also provides some kind of security for usage with user-defined file-paths. Accessing file-attributes or file-contents is only done after running some constraint-checks.

### Constraints

The following constraints are set as default, but can be overwritten with the second argument of the `File($storage, $constraints)` or `Directory($storage, $constraints)` constructor:

 - `Constraint::IN_OPENBASEDIR` => the path must be withing the `open_basedir` php-ini paths, this allows throwing exceptions befor running into phps own error
 - `Constraint::DISALLOW_LINK` => the path must not be a link
 - `Constraint::IN_SAFEPATH` => is a file/directory path is build out of multiple parts, all laters parts must be within the first

 ```php
 use ricwein\FileSystem\File;
 use ricwein\FileSystem\Storage;
 use ricwein\FileSystem\Helper\Constraint;

 $filename = $_GET['file'];
 // let's asume $filename = '/../file.txt'

 // path concatenated as a single string
 // this runs fine but is HIGHLY UNRECOMMENDED
 $file = new File(new Storage\Disk(__DIR__ . $_GET['file']));

 // path is given as single parts (see comma instead of dot)
 // this throws an error since the resulting path is not within __DIR__
 $file = new File(new Storage\Disk(__DIR__, $_GET['file']));

 // disabling the safepath-constraint wold also allow ../ path attacks:
 $file = new File(new Storage\Disk(__DIR__ . $_GET['file']), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
 ```

## Extensions

 - `Directory\Command`: Allows running shell-commands inside the given directory.

 ```php
 $git = new Directory\Command(new Storage\Disk(__DIR__), Constraint::STRICT, ['/usr/local/bin/git', '/usr/bin/git']);
 $ref = $git->execSafe('rev-parse HEAD');
 ```

### Storage Extensions

 - `Disk\Current`: Uses current-working-directory (`getcwd()`) as safepath. Usefull for cli-scripts in combination with `Directory\Command`.

 ```php
 $current = new File(new Storage\Disk(getcwd(), 'file.json'), Constraints::STRICT & ~Constraint::IN_SAFEPATH);
 // is the same as:
 $current = new File(new Storage\Disk\Current('file.json'));
 ```

 ```php
 $git = new Command(new Storage\Disk\Current(), Constraint::STRICT, ['/usr/local/bin/git', '/usr/bin/git']);

 if (!$git->execSafe('pull $branch', ['branch' => 'develop'])) {
     echo 'failed to execute: ' . $git->getLastCommand() . PHP_EOL;
 }

 exit($git->lastExitCode());
 ```

 - `Disk\Temp`: Uses the system-temp directory to create a temporary file/directory. The file is automatically removed after freeing the object instance!

 ```php
 $temp = new File(new Storage\Disk\Temp());
 $temp->write('test');
 $temp->read();
 $temp = null; // will delete the temp-file again!
 ```

 - `Disk\Uploaded`: Provides safe and easy uploaded-files access through native `is_uploaded_file()` and `move_uploaded_file()` functions.

 ```php
 $uploaded = new File(new Storage\Disk\Uploaded($_FILES['file']));
 $file = $uploaded->moveTo(new Storage\Disk(__DIR__, 'uploads'));
 ```

 - `Memory\Resource`: Reads resource content into memory on construct. The resource can be closed afterwards.

 ```php
 $resource = fopen('test.json', 'r');
 $file = new File(new Storage\Memory\Resource($resource));
 fclose($resource);
 $content = $file->read();
 ```

### Binary access
<!-- TODO -->
