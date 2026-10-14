<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Conversion;

use Domain\Knowledge\Ingestion\Exceptions\DocumentParsingException;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageManagerInterface;
use Throwable;

/**
 * Gets an image ready for a document reader: a PNG copy, for a format the
 * reader does not take (GIF, WebP and AVIF for Azure; HEIC for Mistral), or
 * for a size it does not take (Azure: 50 to 10,000 pixels a side). Uses
 * Imagick when it is installed: it reads HEIC, and large images, better
 * than GD. The copy is a temp file the caller deletes.
 */
final class ReaderImage
{
    /**
     * A PNG copy of the image, fitted between the shortest and longest side
     * given (in pixels).
     */
    public function png(string $path, int $minSide = 1, int $maxSide = PHP_INT_MAX): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        try {
            $image = $this->manager()->decodePath($path);
            $width = $image->width();
            $height = $image->height();

            if (max($width, $height) > $maxSide) {
                $image->scaleDown($maxSide, $maxSide);
            } elseif (min($width, $height) < $minSide) {
                $factor = $minSide / max(1, min($width, $height));
                $image->scale((int) ceil($width * $factor), (int) ceil($height * $factor));
            }

            return create_temp_file('image.png', $image->encode(new PngEncoder)->toString());
        } catch (Throwable) {
            throw DocumentParsingException::unreadableImage($extension);
        }
    }

    /**
     * Whether the image has a side outside the range. False when its size
     * cannot be read without decoding it (such as HEIC): the reader decides.
     */
    public function outside(string $path, int $minSide, int $maxSide): bool
    {
        $size = @getimagesize($path);

        if ($size === false) {
            return false;
        }

        return max($size[0], $size[1]) > $maxSide || min($size[0], $size[1]) < $minSide;
    }

    private function manager(): ImageManagerInterface
    {
        return ImageManager::usingDriver(extension_loaded('imagick') ? ImagickDriver::class : GdDriver::class);
    }
}
