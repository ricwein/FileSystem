<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\File;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Helper\Constraint;

/**
 * test FileSyst\File bases
 *
 * @author Richard Weinhold
 */
class UploadedFileTest extends TestCase
{

    /**
     * @expectedException \ricwein\FileSystem\Exceptions\ConstraintsException
     * @return void
     */
    public function testUploadedFileConstrains()
    {
        $file = new File((new Storage\Disk\Uploaded([
            'tmp_name' => __DIR__ . '/../_examples/test.txt',
            'name' => 'test.txt',
            'error' => 0,
        ]))->removeOnFree(false), Constraint::STRICT & ~Constraint::IN_SAFEPATH);

        $this->assertFalse(is_uploaded_file($file->path()->raw));

        if (!$file->storage()->doesSatisfyConstraints() && null !== $errors = $file->storage()->getConstraintViolations()) {
            throw $errors;
        }
    }

    /**
     * @expectedException \ricwein\FileSystem\Exceptions\UnexpectedValueException
     * @return void
     */
    public function testUploadedFileConstructor()
    {
        $file = new File((new Storage\Disk\Uploaded([
            'name' => 'test.txt',
            'error' => 0,
        ]))->removeOnFree(false), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
    }

    /**
     * @expectedException \ricwein\FileSystem\Exceptions\RuntimeException
     * @return void
     */
    public function testUploadedFileErrors()
    {
        $file = new File((new Storage\Disk\Uploaded([
            'tmp_name' => __DIR__ . '/../_examples/test.txt',
            'name' => 'test.txt',
            'error' => UPLOAD_ERR_INI_SIZE,
        ]))->removeOnFree(false), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
    }
}
