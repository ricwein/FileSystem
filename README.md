# FileSystem

This Library provides a Filesystem abstraction layer.

```php
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Exceptions\Exception as FileSystemException;

try {

    $file = new File(new Storage\Disk(__DIR__, '/test', '/dir2', 'test.json'));
    $alt = new File(new Storage\Memory('{"some": "content"}'));

    // read file into output-buffer
    if ($file->isReadable()) {
        header('Content-Type: ' . $file->getType(true));
        echo $file->read();
    } else {
        header('Content-Type: ' . $alt->getType(true));
        echo $alt->read();
    }

} catch (FileSystemException $e) {

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

- a `Disk` file/directory at your local filesystem (`Storage\Disk`)
- a `Memory` file, which only temporary exists in-memory (`Storage\Memory`)
- a `Stream` a stream which points to a file or resource (`Storage\Stream`)
- an abstract `Flysystem` file (`Storage\Flysystem`)

All Storage-Types must extend the abstract base class `Filesystem\Storage`.

> WARNING: since storage-objects are mutable and php automatically handles class-objects as references when passed into a function (constructor), it's highly recommended using the `clone` keyword when storages are recycled between to FileSystem-Objects.
> DO NOT:

```php
$originalDir = new Directory(new Storage\Disk(__DIR__));
$copyDir = new Directory($originalDir->storage());

$copyDir->cd('test'); // will also changes $originalDir path!
```

> DO:

```php
$originalDir = new Directory(new Storage\Disk(__DIR__));
$copyDir = new Directory(clone $originalDir->storage());

$copyDir->cd('test'); // $originalDir will stay in __DIR__
```

### Exceptions

Accessing File/Directory Attributes can result in throwing Exceptions. All Exceptions extend `Exceptions\Exception`.

## Usage: Files

All *FileSystem*-base-classes must be initialized using a Storage.

### Methods

| method | description |
|---|---|
| `read(?$offset, ?$length, $mode)` | read and return file-content, allows partial read with `$offset` and `$length` parameters, file is locked while reading with `$mode`|
| `stream(?$offset, ?$length, $mode)` | stream file into output-buffer, file is locked while reading with `$mode`|
| `write($content, $append, $mode)` | write `$content` to file, creates new file if it doesn't already exists, allows appended writing if `$append` isset, locks file with `$mode` |
| `copyTo($destination [,Constraint $constraints])` | copy file to new `$destination` Storage-adapter |
| `moveTo($destination [,Constraint $constraints])` | like `copyTo()`, but moves files instead |
| `touch([bool $ifNewOnly])` | create file if it doesn't exists, updates last-modified timestamp |
| `remove()` | try to remove file |
| `getType([bool $withEncoding])` | guess files mime-type |
| `getTime([Time $type])` | get last-modified unix-timestamp |
| `getDate([Time $type])` | same as  `getTime()`, but returns a DateTime object instead |
| `getSize()` | calculate size |
| `getHash([Hash $mode [,string $algo [,bool $raw]]])` | calculates a hash over `$mode` with `$algo` algorithm |
| `isReadable()` | is file readable? |
| `isWriteable()` | is file writeable? |
| `isSymlink()` | is file a symlink? |
| `isFile()` | is selected path an actual file? |
| `isDir()` | is selected path a directory? => always `false` for File instance |
| `isDotfile()` | is file a hidden dot-file? |
| `isValid()` | run constraints validation |
| `getHandle([string $mode])` | gets new file-Handle for binary file-access |
| `storage()` | access internal storage adapter |
| `path()` | tries to access filesystem-path |
| `getStream()` | returns `Stream` wrapper around internal resource pointing to actual file |
| `dir([,int $constraints [,string $as [,...$arguments]]])` | get parent `Directory` of file |

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

### or from stream

```php
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;

$file = new File(new Storage\Stream(fopen('php://output')));
$content = $file->write('content');
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
| `list($recursive)` | returns DirectoryIterator-Object to list directory-content as new `File`/`Directory` objects |
| `mkdir()` | try to create directory |
| `remove()` | try to remove directory |
| `getTime([Time $type])` | get last-modified unix-timestamp |
| `getDate([Time $type])` | same as  `getTime()`, but returns a DateTime object instead |
| `getSize([bool $recursive])` | calculate size |
| `getHash([Hash $mode [,string $algo [,bool $raw]]])` | calculates a hash over `$mode` with `$algo` algorithm |
| `isReadable()` | is directory readable? |
| `isWriteable()` | is directory writeable? |
| `isSymlink()` | is directory a symlink? |
| `isFile()` | is selected path a file? => always `false` for Directory instance |
| `isDir()` | is selected path an actual directory? |
| `isDotfile()` | is directory a hidden dot-file? |
| `isValid()` | run constraints validation |
| `storage()` | access internal storage adapter |
| `path()` | tries to access filesystem-path |
| `file(string $filename [,int $constraints [,string $as [,...$arguments]]])` | get `File` in current directory by name |
| `dir(string $dirname [,int $constraints [,string $as [,...$arguments]]])` | get `Directory` in current directory by name (`clone`s storage!) |

### check if directory is readable

```php
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Storage;

$dir = new Directory(new Storage\Disk(__DIR__));
var_dump($dir->isReadable());
```

### list all files inside a directory

```php
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Storage;

$hashes = [];
$dir = new Directory(new Storage\Disk(__DIR__));

foreach($dir->list(true)->files() as $file) {
    $hashes[$file->path()->filename] = $file->getHash();
}
```

## Security

Using this filesystem-layer also provides some kind of security for usage with user-defined file-paths. Accessing file attributes or content is only done after checking against so called ***Constraints***.

### Constraints

Constraints are defined on initialization of `File` or `Directory` objects and are stored inside the internal `Storage` object. This allows Constraints-inheritance, if new FileSystem-objects are accessed from existing ones. Example:

```php
use ricwein\FileSystem\Directory;
use ricwein\FileSystem\Storage;
 use ricwein\FileSystem\Helper\Constraint;

$dir = new Directory(new Storage\Disk(__DIR__), Constraint::STRICT);
$file = $dir->file($_GET['filename']);
```

In this example, the `$file` object shares the ***Constraints*** (inherited) and ***safepath*** with `$dir` - allowing safely accessing a file in `$dir` from user defined parameters. Path traversal is therefore prevented.

The following constraints are set as default (as part of `Constraint::STRICT`), but can be overwritten with the second argument of the `File($storage, $constraints)` or `Directory($storage, $constraints)` constructor:

- `Constraint::IN_OPENBASEDIR` => the path must be within the `open_basedir` php-ini paths, this allows throwing exceptions before running into php core errors
- `Constraint::DISALLOW_LINK` => the path must not be a (symbolic-) link
- `Constraint::IN_SAFEPATH` => if a file/directory path is build out of multiple components (parameters), the resulting file/directory destination must be inside the first path-component (called ***safepath***)

 ```php
 use ricwein\FileSystem\File;
 use ricwein\FileSystem\Storage;
 use ricwein\FileSystem\Helper\Constraint;

 // let's assume $_GET['file'] == '/../file.txt'

 // path concatenated as a single string
 // this runs fine but is HIGHLY UNRECOMMENDED
 $file = new File(new Storage\Disk(__DIR__ . $_GET['file']));

 // path is passed as single parameters (comma instead of dot!)
 // this throws an error since the resulting path is not within __DIR__
 $file = new File(new Storage\Disk(__DIR__, $_GET['file']));

 // however: disabling the safepath-constraint wold also allow ../ path attacks:
 $file = new File(new Storage\Disk(__DIR__, $_GET['file']), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
 ```

## Extensions

### Directory Extensions

- `Directory\Command`: Allows running shell-commands inside the given directory.

```php
$git = new Directory\Command(new Storage\Disk(__DIR__), Constraint::STRICT, ['/usr/local/bin/git', '/usr/bin/git']);
$ref = $git->execSafe('rev-parse HEAD');
```

### File Extensions

- `File\Image`: Allows image-manipulations based on `imagemagick` or `gd` (later one is default). Requires the `Intervention\Image` package.

> Be aware: all image-file manipulations are directly mutating the original file!

```php
$image = new File\Image(new Storage\Disk('test.png'));
$image->resizeToFit(1024, 1024);
$image->compress(1048576); // iterative process to reduce filesize to be less than given filesize (1MB) by reducing the jpg-quality
// $image->encode('jpg');
$image->edit(function (Intervention\Image $image): Intervention\Image {
    // add advanced image-manipulation here
    return $image;
});
```

- `File\Zip`: Allows basic zip-operations, like creating a new archive or extracting an existing one.

```php
$zip = new File\Zip(new Storage\Disk('archive.zip'));

// create zip file
$zip->add(new File(new Storage\Disk('file.json'))); // or $zip->addFile(...)
$zip->add(new File(new Storage\Memory('some file-content')), 'anotherfile.txt'); // or $zip->addFile(...)
$zip->add(new Directory(new Storage\Disk(__DIR__, 'data-dir'))); // or $zip->addDirectory(...)
$zip->commit();

// extract zip file
$extractDir = $zip->extractTo(new Storage\Disk\Temp);
```

### Storage Extensions

- `Disk\Current`: Uses current-working-directory (`getcwd()`) as safepath. Useful for cli-scripts in combination with `Directory\Command`.

 ```php
 $current = new File(new Storage\Disk(getcwd(), 'file.json'), Constraints::STRICT & ~Constraint::IN_SAFEPATH);
 // is the same as:
 $current = new File(new Storage\Disk\Current('file.json'));
 ```

 ```php
 $git = new Command(new Storage\Disk\Current, Constraint::STRICT, ['/usr/local/bin/git', '/usr/bin/git']);

 if (!$git->execSafe('pull $branch', ['branch' => 'develop'])) {
     echo 'failed to execute: ' . $git->getLastCommand() . PHP_EOL;
 }

 exit($git->lastExitCode());
 ```

- `Disk\Temp`: Uses the system-temp directory to create a temporary file/directory. The file is automatically removed after freeing the object instance!

 ```php
 $temp = new File(new Storage\Disk\Temp);
 $temp->write('test');
 $temp->read();
 $temp = null; // will delete the temp-file again!
 ```

- `Disk\Uploaded`: Provides safe and easy *uploaded-files* access through php's native `is_uploaded_file()` and `move_uploaded_file()` functions.

 ```php
 $uploaded = new File(new Storage\Disk\Uploaded($_FILES['file']));
 $file = $uploaded->moveTo(new Storage\Disk(__DIR__, 'uploads'));
 ```

- `Memory\Resource`: Reads resource content into **MEMORY** on construction. The resource can be closed afterwards.

> ATTENTION: Usually it's a better idea to just use `Stream` instead!

 ```php
 $resource = fopen('test.json', 'rb');
 $file = new File(new Storage\Memory\Resource($resource));
 fclose($resource);
 $content = $file->read();
 ```
