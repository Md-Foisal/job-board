<?php

namespace App\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Swap one stored image for another, cleaning up the old file.
 *
 * Every image field in the app wants the same three things: leave the
 * existing path alone when nothing was uploaded, delete what was there
 * before when something was, and hand back the new path. Written out at
 * each call site that is three chances to forget the delete and leave
 * orphans behind on disk.
 *
 * The picture is not stored as it arrived. It is decoded and written out
 * again, which keeps the pixels and drops everything else: camera metadata
 * such as the GPS position a phone embeds in every photo, and anything
 * smuggled into the file that is not part of the image itself. This is the
 * "image rewriting" the OWASP file-upload guidance recommends. Because the
 * orientation flag goes with the metadata, it is applied to the pixels
 * first, or portrait phone photos would come out sideways.
 */
class ReplaceUploadedImage
{
    /**
     * The default ceiling: anything larger is scaled down to this before it
     * is stored, the same threshold WordPress applies to every upload.
     * Callers pass a smaller one for pictures only ever shown small. It is sharp on any
     * screen a logo or profile photo is shown on, and it keeps the rest of
     * the work -- rotating a portrait phone photo makes a second full copy
     * in memory -- well inside a normal web request's memory limit.
     */
    public const LONGEST_SIDE = 2560;

    public function __invoke(
        ?UploadedFile $file,
        ?string $existingPath,
        string $directory,
        string $disk = 'public',
        int $longestSide = self::LONGEST_SIDE,
    ): ?string {
        if (! $file) {
            return $existingPath;
        }

        [$contents, $extension] = $this->rewrite($file, $longestSide);

        $path = $directory.'/'.Str::random(40).'.'.$extension;
        Storage::disk($disk)->put($path, $contents);

        if ($existingPath) {
            Storage::disk($disk)->delete($existingPath);
        }

        return $path;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function rewrite(UploadedFile $file, int $longestSide): array
    {
        $source = $file->getRealPath();
        $image = @imagecreatefromstring((string) file_get_contents($source));

        if ($image === false) {
            // Validation already decoded this file once; reaching here
            // means it changed underneath us, which is not a user error.
            throw new RuntimeException('An uploaded image could not be decoded.');
        }

        $image = $this->fitWithin($image, $longestSide);

        $mime = $file->getMimeType();

        if ($mime === 'image/jpeg') {
            $image = $this->applyOrientation($image, $source);
        }

        ob_start();

        $extension = match ($mime) {
            'image/png' => $this->writePng($image),
            'image/webp' => $this->writeWebp($image),
            default => $this->writeJpeg($image),
        };

        $contents = (string) ob_get_clean();

        return [$contents, $extension];
    }

    private function writeJpeg(\GdImage $image): string
    {
        imagejpeg($image, null, 85);

        return 'jpg';
    }

    private function writePng(\GdImage $image): string
    {
        imagesavealpha($image, true);
        imagepng($image);

        return 'png';
    }

    private function writeWebp(\GdImage $image): string
    {
        imagesavealpha($image, true);
        imagewebp($image, null, 85);

        return 'webp';
    }

    private function applyOrientation(\GdImage $image, string $source): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($source)['Orientation'] ?? 1);

        return match ($orientation) {
            2 => $this->flipped($image, IMG_FLIP_HORIZONTAL),
            3 => $this->rotated($image, 180),
            4 => $this->flipped($image, IMG_FLIP_VERTICAL),
            5 => $this->flipped($this->rotated($image, -90), IMG_FLIP_HORIZONTAL),
            6 => $this->rotated($image, -90),
            7 => $this->flipped($this->rotated($image, 90), IMG_FLIP_HORIZONTAL),
            8 => $this->rotated($image, 90),
            default => $image,
        };
    }

    private function fitWithin(\GdImage $image, int $longestSide): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if (max($width, $height) <= $longestSide) {
            return $image;
        }

        $scale = $longestSide / max($width, $height);
        $scaled = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));

        if ($scaled === false) {
            return $image;
        }

        imagesavealpha($scaled, true);

        return $scaled;
    }

    private function rotated(\GdImage $image, int $degrees): \GdImage
    {
        return imagerotate($image, $degrees, 0) ?: $image;
    }

    private function flipped(\GdImage $image, int $mode): \GdImage
    {
        imageflip($image, $mode);

        return $image;
    }
}
