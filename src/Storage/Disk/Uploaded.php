<?php

/**
 * @author Richard Weinhold
 */

declare(strict_types=1);

namespace ricwein\FileSystem\Storage\Disk;

use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Path;
use ricwein\FileSystem\Storage;
use ricwein\FileSystem\Storage\BaseStorage;

/**
 * like Disk, but for temporary files
 */
class Uploaded extends Storage\Disk
{
    protected bool $selfDestruct = true;

    private const UPLOAD_ERRORS = [
        UPLOAD_ERR_INI_SIZE => 'The file "%s" exceeds your upload_max_filesize ini directive.',
        UPLOAD_ERR_FORM_SIZE => 'The file "%s" exceeds the upload limit defined in your form.',
        UPLOAD_ERR_PARTIAL => 'The file "%s" was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_CANT_WRITE => 'The file "%s" could not be written to disk.',
        UPLOAD_ERR_NO_TMP_DIR => 'File could not be uploaded: missing temporary directory.',
        UPLOAD_ERR_EXTENSION => 'File upload was stopped by a PHP extension.',
    ];

    protected ?ConstraintsException $previousConstraintError = null;

    /**
     * original file-name
     */
    protected string $name;

    /**
     * @param array $file $_FILE array in the format:
     *  ['tmp_name' => string, 'name' => string, 'error' => int]
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function __construct(array $file)
    {
        if (!isset($file['tmp_name']) || !is_string($file['tmp_name'])) {
            throw new UnexpectedValueException('invalid or missing \'tmp_name\'', 500);
        }

        parent::__construct(new Path($file['tmp_name']));

        if (array_key_exists('error', $file) && is_int($file['error'])) {
            $this->checkUploadError($file['error']);
        }

        if (!isset($file['name']) || !is_string($file['name'])) {
            $this->name = basename($file['tmp_name']);
        } else {
            $this->name = $file['name'];
        }
    }

    /**
     * get original upload name if available
     */
    public function getOriginalName(): string
    {
        return $this->name;
    }

    /**
     * @throws RuntimeException
     */
    protected function checkUploadError(int $error): void
    {
        if (isset(self::UPLOAD_ERRORS[$error])) {
            throw new RuntimeException(sprintf(self::UPLOAD_ERRORS[$error], $this->path->getRawPath()), 500);
        }
    }

    /**
     * @inheritDoc
     */
    public function doesSatisfyConstraints(): bool
    {
        if (!is_uploaded_file($this->path->getRawPath())) {
            $this->previousConstraintError = new ConstraintsException('invalid uploaded file', 500);
            return false;
        }

        return parent::doesSatisfyConstraints();
    }

    /**
     * @inheritDoc
     */
    public function getConstraintViolations(ConstraintsException $previous = null): ?ConstraintsException
    {
        return parent::getConstraintViolations($previous ?? $this->previousConstraintError);
    }

    /**
     * @inheritDoc
     */
    public function setConstraints(int $constraints): static
    {
        return parent::setConstraints($constraints & ~Constraint::IN_SAFEPATH);
    }

    /**
     * @inheritDoc
     */
    public function moveFileTo(BaseStorage $destination): bool
    {
        switch (true) {

            // use native safe-move function for uploaded files
            case $destination instanceof Storage\Disk:
                if (!move_uploaded_file($this->path->getRealPath(), $destination->getPath()->getRawPath())) {
                    return false;
                }
                $destination->getPath()->reload();
                return true;

            // use a temp-file for safe-move before moving file into destination-storage
            case $destination instanceof Storage\Flysystem:
            case $destination instanceof Storage\Memory:
            default:
                $temp = new Storage\Disk\Temp();
                if (!move_uploaded_file($this->path->getRealPath(), $temp->getPath()->getRawPath())) {
                    return false;
                }
                return $temp->moveFileTo($destination);
        }
    }
}
