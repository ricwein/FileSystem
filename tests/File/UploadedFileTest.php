<?php

declare(strict_types=1);

namespace ricwein\FileSystem\Tests\File;

use PHPUnit\Framework\TestCase;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\Exception;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Helper\Constraint;

class UploadedFileTest extends TestCase
{
    /**
     * @throws ConstraintsException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws AccessDeniedException
     * @throws Exception
     */
    public function testUploadedFileConstrains(): void
    {
        $file = new File((new Storage\Disk\Uploaded([
            'tmp_name' => __DIR__ . '/../_examples/test.txt',
            'name' => 'test.txt',
            'error' => 0,
        ]))->removeOnFree(false), Constraint::STRICT & ~Constraint::IN_SAFEPATH);

        self::assertFalse(is_uploaded_file($file->getPath()->getRawPath()));

        $this->expectException(ConstraintsException::class);
        $this->expectExceptionMessage("invalid uploaded file");

        if (!$file->storage()->doesSatisfyConstraints() && null !== $errors = $file->storage()->getConstraintViolations()) {
            throw $errors;
        }
    }

    /**
     * @throws AccessDeniedException
     * @throws Exception
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testUploadedFileConstructor(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("invalid or missing 'tmp_name'");

        new File((new Storage\Disk\Uploaded([
            'name' => 'test.txt',
            'error' => 0,
        ]))->removeOnFree(false), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
    }

    /**
     * @throws AccessDeniedException
     * @throws Exception
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function testUploadedFileErrors(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/The file.*exceeds your upload_max_filesize ini directive\./');

        new File((new Storage\Disk\Uploaded([
            'tmp_name' => __DIR__ . '/../_examples/test.txt',
            'name' => 'test.txt',
            'error' => UPLOAD_ERR_INI_SIZE,
        ]))->removeOnFree(false), Constraint::STRICT & ~Constraint::IN_SAFEPATH);
    }
}
