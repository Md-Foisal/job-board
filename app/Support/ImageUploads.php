<?php

namespace App\Support;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * The one definition of what counts as an acceptable picture -- a logo,
 * a profile photo, a cover image -- so every upload field agrees.
 *
 * Only the formats every browser displays: JPEG, PNG and WebP. Laravel's
 * plain `image` rule also lets through GIF, BMP, AVIF and HEIC; HEIC is
 * what an iPhone saves by default and only Safari can show it, so it
 * would upload happily and then appear as a broken image on the public
 * page. SVG stays out as well: it is a document that can carry script,
 * not a picture.
 *
 * The type check reads the file's contents, not its name. On top of that
 * the picture must actually decode, and its pixel size is capped: a small
 * file can describe an enormous canvas, and decoding that is what exhausts
 * memory.
 */
final class ImageUploads
{
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * For the file input's `accept` attribute, so the picker only offers
     * files the server will take.
     */
    public const ACCEPT = 'image/jpeg,image/png,image/webp';

    /**
     * Wide enough for any phone camera (4032 x 3024), small enough to
     * decode inside the default memory limit.
     */
    public const MAX_PIXELS_PER_SIDE = 4096;

    /**
     * @return array<int, mixed>
     */
    public static function rules(int $maxKilobytes): array
    {
        return [
            File::types(self::EXTENSIONS)->max($maxKilobytes),
            Rule::dimensions()->maxWidth(self::MAX_PIXELS_PER_SIDE)->maxHeight(self::MAX_PIXELS_PER_SIDE),
            function (string $attribute, mixed $value, Closure $fail): void {
                if ($value instanceof UploadedFile && ! self::decodes($value)) {
                    $fail(__('The :attribute could not be read as a picture.'));
                }
            },
        ];
    }

    private static function decodes(UploadedFile $file): bool
    {
        $contents = @file_get_contents($file->getRealPath());

        return $contents !== false && @imagecreatefromstring($contents) !== false;
    }
}
