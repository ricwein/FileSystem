<?php

/**
 * @author Richard Weinhold
 */

declare(strict_types=1);

namespace ricwein\FileSystem\File;

use Intervention\Image\Constraint as IConstraint;
use Intervention\Image\Image as IImage;
use Intervention\Image\ImageManager as IImageManager;
use ricwein\FileSystem\Exceptions\AccessDeniedException;
use ricwein\FileSystem\Exceptions\ConstraintsException;
use ricwein\FileSystem\Exceptions\FileNotFoundException;
use ricwein\FileSystem\Exceptions\RuntimeException;
use ricwein\FileSystem\Exceptions\UnexpectedValueException;
use ricwein\FileSystem\Exceptions\UnsupportedException;
use ricwein\FileSystem\File;
use ricwein\FileSystem\Helper\Constraint;
use ricwein\FileSystem\Helper\MimeType;
use ricwein\FileSystem\Storage;

/**
 * use gd/imagemagick for image-manipulations
 * @method Image copyTo(Storage $destination, ?int $constraints = null)
 */
class Image extends File
{
    protected IImageManager $manager;

    /**
     * @inheritDoc
     * @throws UnsupportedException
     */
    public function __construct(Storage $storage, int $constraints = Constraint::STRICT, string $driver = 'gd')
    {
        parent::__construct($storage, $constraints);

        if (!in_array($driver, ['gd', 'imagemagick'], true)) {
            throw new UnsupportedException(sprintf('unsupported image-driver \'%s\'', $driver), 400);
        }

        $this->manager = new IImageManager(['driver' => $driver]);
    }

    /**
     * @throws RuntimeException
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws UnexpectedValueException
     */
    public function edit(callable $callback): self
    {
        $mimetype = $this->getType();

        if (!MimeType::isImage($mimetype)) {
            throw new RuntimeException('unspported mimetype for image-manipulation', 400);
        }

        // fetch image (path or content)
        $imageData = $this->storage instanceof Storage\Disk ? $this->storage->path()->real : $this->read();
        $image = $callback($this->manager->make($imageData));

        /** @var IImage $image */
        if (!$image instanceof IImage) {
            throw new RuntimeException(sprintf(
                'callback must return a object of type "%s", but "%s" given',
                IImage::class,
                get_debug_type($image)
            ), 400);
        }

        // encode image if not already done
        if (!$image->isEncoded()) {
            $image->encode(MimeType::getExtensionFor($image->mime()) ?? 'jpg');
        }

        // save image into file (memory/disk/flysystem)
        $this->write((string)$image);

        if ($this->storage instanceof Storage\Disk) {
            $this->storage->path()->reload();
        }

        return $this;
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function encode(string $newFormat, ?float $quality = null): self
    {
        return $this->edit(function (IImage $image) use ($newFormat, $quality): IImage {
            return $image->encode($newFormat, $quality);
        });
    }

    /**
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function resize(?int $width = null, ?int $height = null, bool $aspectRatio = true): self
    {
        if ($width === null && $height === null) {
            throw new RuntimeException('at least one of width or height must be specified for resizing, but null given', 404);
        }

        return $this->edit(function (IImage $image) use ($width, $height, $aspectRatio): IImage {
            return $image->resize($width, $height, function (IConstraint $constraint) use ($aspectRatio) {
                if ($aspectRatio) {
                    $constraint->aspectRatio();
                }
            });
        });
    }

    /**
     * @param bool $upsize allows upsizing of the image
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function resizeToFit(int $width, int $height, bool $upsize = false): self
    {
        return $this->edit(function (IImage $image) use ($width, $height, $upsize): IImage {

            // fetch properties
            $imageWidth = $image->width();
            $imageHeight = $image->height();

            // image is already smaller than the given size
            if (!$upsize && $imageWidth <= $width && $imageHeight <= $height) {
                return $image;
            }

            // resize landscape/square image
            if ($imageWidth >= $imageHeight) {
                $image->resize($width, null, function (IConstraint $constraint) {
                    $constraint->aspectRatio();
                });
            } else {
                $image->resize(null, $height, function (IConstraint $constraint) {
                    $constraint->aspectRatio();
                });
            }
            return $image;
        });
    }

    /**
     * @param int $filesize destination filesize in bytes
     * @throws AccessDeniedException
     * @throws ConstraintsException
     * @throws FileNotFoundException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function compress(int $filesize, ?string $format = 'jpg', int $minQuality = 0): self
    {
        if ($format === null) {
            $format = MimeType::getExtensionFor($this->getType());
        }

        return $this->edit(function (IImage $image) use ($filesize, $format, $minQuality): IImage {
            for ($quality = 100; $quality >= $minQuality; $quality -= 5) {

                // re-encode image
                $encodedImage = $image->encode($format, $quality);

                if (mb_strlen((string)$encodedImage, '8bit') <= $filesize) {
                    return $encodedImage;
                }
            }

            throw new RuntimeException(sprintf('unable to reduce filesize to less than %1.2f MB', $filesize / (2 ** 20)), 400);
        });
    }
}
