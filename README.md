# FileSystem

This Library provides an Filesystem abstraction layer.

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

- a "real" file at the local Disk (`Storage\Disk`)
- a "virtual" file, which only temporary exists in-memory (`Storage\Memory`)

All Storage-Types must extend the abstract base class `Storage`.

### Exceptions

Accessing File/Directory Attributes can result in throwing Exceptions. All Exceptions extend `Exceptions\Exception`.

## Usage

All *FileSystem*-base-classes must be initialized using a Storage.

## Open and read a file from the local filesystem

```php
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;

$file = new File(new Storage\Disk(__DIR__, 'test.txt'));
$content = $file->read();
```

## or from in-memory-file

```php
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;

$file = new File(new Storage\Memory('some content'));
$content = $file->read();
```
