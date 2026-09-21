<?php

namespace App\Support;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * What each kind of picture in the app has to be, in one place, so the
 * validation, the stored size and the hint under the upload field can
 * never disagree.
 *
 * Only the formats every browser displays: JPEG, PNG and WebP. Laravel's
 * plain `image` rule also lets through GIF, BMP, AVIF and HEIC; HEIC is
 * what an iPhone saves by default and only Safari can show it, so it
 * would upload happily and then appear as a broken image. SVG stays out
 * as well: it is a document that can carry script, not a picture.
 *
 * The three kinds follow LinkedIn's published specifications, the closest
 * established reference for a professional profile:
 *
 * - photo (candidate and recruiter): at least 300 x 300, as LinkedIn asks
 *   for profile photos; anything smaller looks soft in the circle it is
 *   shown in. Any shape is accepted and cropped to a circle on display.
 * - logo: the same minimum LinkedIn sets for company logos.
 * - cover (candidate and company banners): at least 1200 x 300, LinkedIn's
 *   safe area for a 1584 x 396 banner, which is also roughly the shape our
 *   banners are drawn at.
 *
 * Each picture is also capped at 4096 pixels a side before it is decoded
 * -- a small file can describe an enormous canvas -- and is stored no
 * larger than it will ever be shown.
 */
final class ImageUploads
{
    public const PHOTO = 'photo';

    public const LOGO = 'logo';

    public const COVER = 'cover';

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
     * @var array<string, array{max_kilobytes: int, min_width: int, min_height: int, stored_longest_side: int}>
     */
    private const SPECS = [
        self::PHOTO => ['max_kilobytes' => 2048, 'min_width' => 300, 'min_height' => 300, 'stored_longest_side' => 800],
        self::LOGO => ['max_kilobytes' => 2048, 'min_width' => 300, 'min_height' => 300, 'stored_longest_side' => 800],
        self::COVER => ['max_kilobytes' => 4096, 'min_width' => 1200, 'min_height' => 300, 'stored_longest_side' => 2560],
    ];

    /**
     * @return array<int, mixed>
     */
    public static function rules(string $kind): array
    {
        $spec = self::SPECS[$kind];

        return [
            File::types(self::EXTENSIONS)->max($spec['max_kilobytes']),
            Rule::dimensions()
                ->minWidth($spec['min_width'])
                ->minHeight($spec['min_height'])
                ->maxWidth(self::MAX_PIXELS_PER_SIDE)
                ->maxHeight(self::MAX_PIXELS_PER_SIDE),
            function (string $attribute, mixed $value, Closure $fail): void {
                if ($value instanceof UploadedFile && ! self::decodes($value)) {
                    $fail(__('The :attribute could not be read as a picture.'));
                }
            },
        ];
    }

    /**
     * The longest side the picture is scaled down to before it is stored:
     * about twice the size it is displayed at, so it stays sharp on high
     * density screens without keeping a phone camera's full resolution.
     */
    public static function storedLongestSide(string $kind): int
    {
        return self::SPECS[$kind]['stored_longest_side'];
    }

    /**
     * The line shown under the upload field, built from the same numbers
     * the validation uses.
     */
    public static function hint(string $kind): string
    {
        $spec = self::SPECS[$kind];

        return match ($kind) {
            self::COVER => __('JPG, PNG or WebP, at least :width × :height pixels (1584 × 396 fits best), up to :mb MB.', [
                'width' => $spec['min_width'],
                'height' => $spec['min_height'],
                'mb' => $spec['max_kilobytes'] / 1024,
            ]),
            default => __('JPG, PNG or WebP, at least :width × :height pixels (a square works best), up to :mb MB.', [
                'width' => $spec['min_width'],
                'height' => $spec['min_height'],
                'mb' => $spec['max_kilobytes'] / 1024,
            ]),
        };
    }

    private static function decodes(UploadedFile $file): bool
    {
        $contents = @file_get_contents($file->getRealPath());

        return $contents !== false && @imagecreatefromstring($contents) !== false;
    }
}
