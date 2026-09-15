<?php

namespace App\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Swap one stored image for another, cleaning up the old file.
 *
 * Every image field in the app wants the same three things: leave the
 * existing path alone when nothing was uploaded, delete what was there
 * before when something was, and hand back the new path. Written out at
 * each call site that is three chances to forget the delete and leave
 * orphans behind on disk.
 */
class ReplaceUploadedImage
{
    public function __invoke(
        ?UploadedFile $file,
        ?string $existingPath,
        string $directory,
        string $disk = 'public',
    ): ?string {
        if (! $file) {
            return $existingPath;
        }

        if ($existingPath) {
            Storage::disk($disk)->delete($existingPath);
        }

        return $file->store($directory, $disk);
    }
}
