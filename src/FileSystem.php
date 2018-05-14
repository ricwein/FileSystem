<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem;

use ricwein\FileSystem\Storage\Storage;

/**
 * base of all FileSystem type-classes (File/Directory)
 */
abstract class FileSystem {

	/**
	 * @var Storage
	 */
	protected $storage;

	/**
	 * @param Storage $storage
	 */
	__construct(Storage $storage) {
		$this->storage = $storage;
	}
}