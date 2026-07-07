<?php

declare(strict_types=1);

namespace App\Core;

use Imagick;

/**
 * Image processing via Imagick. Used for every uploaded image so we:
 *   1. never depend on GD's JPEG support (the app's GD build lacks it), and
 *   2. STRIP ALL METADATA (EXIF, GPS, colour profiles, comments) on every write
 *      — on a Tor marketplace, an uploader's camera/location data must never be
 *      hosted back to buyers.
 */
class ImageProcessor
{
    /**
     * Strip metadata, optionally downscale to fit $maxW x $maxH (aspect
     * preserved), and write back to $path in place. Returns [width, height] of
     * the result. Metadata is stripped even when no resize is needed.
     */
    public static function sanitize(string $path, int $maxW, int $maxH, int $quality = 85): array
    {
        $img = new Imagick($path);
        try {
            $img->setIteratorIndex(0);        // first frame only (drop animation)
            $img->stripImage();               // remove all metadata

            if ($img->getImageWidth() > $maxW || $img->getImageHeight() > $maxH) {
                // bestfit resize, aspect ratio preserved
                $img->resizeImage($maxW, $maxH, Imagick::FILTER_LANCZOS, 1, true);
            }

            if (strtolower($img->getImageFormat()) === 'jpeg') {
                $img->setImageCompressionQuality($quality);
            }

            $img->stripImage();               // strip again after any re-encode
            $img->writeImage($path);

            return ['width' => $img->getImageWidth(), 'height' => $img->getImageHeight()];
        } finally {
            $img->clear();
            $img->destroy();
        }
    }

    /**
     * Center-cropped square thumbnail (metadata-stripped) written to $dst as
     * JPEG. Used for avatars and listing thumbnails.
     */
    public static function squareThumb(string $src, string $dst, int $size): void
    {
        $img = new Imagick($src);
        try {
            $img->setIteratorIndex(0);
            $img->stripImage();
            $img->cropThumbnailImage($size, $size);   // cover-crop to a square
            $img->stripImage();
            $img->setImageFormat('jpeg');
            $img->setImageCompressionQuality(85);
            $img->writeImage($dst);
        } finally {
            $img->clear();
            $img->destroy();
        }
    }
}
