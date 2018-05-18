<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\FileSystem\Storage\Disk;

use ricwein\FileSystem\Helper\Path;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Storage\Disk;
use ricwein\FileSystem\Storage\Storage;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;

/**
 * like Disk, but for temporary files
 */
class Uploaded extends Disk
{

    /**
     * @var array
     */
    private const UPLOAD_ERRORS = [
        UPLOAD_ERR_INI_SIZE   => 'The file "%s" exceeds your upload_max_filesize ini directive.',
        UPLOAD_ERR_FORM_SIZE  => 'The file "%s" exceeds the upload limit defined in your form.',
        UPLOAD_ERR_PARTIAL    => 'The file "%s" was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_CANT_WRITE => 'The file "%s" could not be written to disk.',
        UPLOAD_ERR_NO_TMP_DIR => 'File could not be uploaded: missing temporary directory.',
        UPLOAD_ERR_EXTENSION  => 'File upload was stopped by a PHP extension.',
    ];

    /**
     * @var ConstraintsException|null
     */
    protected $previousConstraintError = null;

    /**
     * @var string
     */
    protected $name;

    /**
     * @param array $file
     * @throws
     */
    public function __construct(array $file)
    {
        if (!isset($file['tmp_name']) || !is_string($file['tmp_name'])) {
            throw new UnexpectedValueException('invalid or missing \'tmp_name\'', 500);
        } elseif (!array_key_exists('error', $file) || !is_int($file['error'])) {
            throw new UnexpectedValueException('invalid or missing \'error\'', 500);
        }

        $this->path = new Path($file['tmp_name']);
        $this->checkUploadError($file['error']);

        if (!isset($file['name']) || !is_string($file['name'])) {
            throw new UnexpectedValueException('invalid or missing \'name\'', 500);
        }

        $this->name = $file['name'];
    }

    /**
     * get original upload name if available
     * @return string
     */
    public function getOriginalName(): string
    {
        return $this->name;
    }

    /**
     * @param int $error
     * @return void
     * @throws RuntimeException
     */
    protected function checkUploadError(int $error): void
    {
        if (isset(self::UPLOAD_ERRORS[$error])) {
            throw new RuntimeException(sprintf(self::UPLOAD_ERRORS[$error], $this->path->raw), 500);
        }
    }
    /**
     * @inheritDoc
     */
    public function doesSatisfyConstraints(): bool
    {
        if (!is_uploaded_file($this->path->raw)) {
            $this->previousConstraintError = new ConstraintsException('invalid uploaded file', 500);
            return false;
        }

        return parent::doesSatisfyConstraints();
    }

    /**
     * @inheritDoc
     */
    public function getConstraintViolations(): ?ConstraintsException
    {
        return parent::getConstraintViolations($this->previousConstraintError);
    }

    /**
     * @inheritDoc
     */
    public function setConstraints(int $constraints): Storage
    {
        return parent::setConstraints($constraints & ~Constraint::IN_SAFEPATH);
    }
}
