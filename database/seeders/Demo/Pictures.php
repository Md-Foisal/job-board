<?php

namespace Database\Seeders\Demo;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pictures for seeded companies and people, copied from the folder next
 * to this class onto the public disk, into the same folders an upload
 * lands in. Every record gets its own copy: erasing an account deletes
 * its files, and that must never take a picture away from anyone else.
 *
 * The logos and banners were drawn for this project. The people are
 * DiceBear's Notionists style (Zoish, CC0 1.0): drawn faces rather than
 * photographs, because a real person's photo beside an invented CV
 * would present a stranger as someone they are not. A face is picked
 * from the set that suits the first name, so Hannah never gets a beard.
 */
final class Pictures
{
    public const FOLDERS = ['avatars', 'candidate-covers', 'company-logos', 'company-covers', 'recruiter-avatars'];

    private const SOURCE = __DIR__.'/pictures';

    /** @var array<string, list<string>> */
    private static array $unused = [];

    /**
     * Uploads left by the previous seed belong to rows that no longer
     * exist. Only ever on a developer machine: these folders hold real
     * people's pictures anywhere else.
     */
    public static function forgetPrevious(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        foreach (self::FOLDERS as $folder) {
            Storage::disk('public')->deleteDirectory($folder);
        }
    }

    public static function companyLogo(string $slug): ?string
    {
        return self::copy("companies/{$slug}-logo.png", 'company-logos');
    }

    /**
     * Not every company has a banner, so the page without one is seen too.
     */
    public static function companyCover(string $slug): ?string
    {
        return self::copy("companies/{$slug}-cover.jpg", 'company-covers');
    }

    public static function face(string $name, string $folder = 'avatars'): string
    {
        $set = self::isFeminine($name) ? 'faces/feminine' : 'faces/masculine';

        return (string) self::copy(self::next($set), $folder);
    }

    public static function personalCover(): string
    {
        return (string) self::copy(self::next('covers'), 'candidate-covers');
    }

    private static function isFeminine(string $name): bool
    {
        foreach (Catalogue::people()['feminine'] as $first) {
            if (str_starts_with($name, $first.' ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Each picture in a set is used once before any is used again, so a
     * page of applicants does not show the same face twice in a row.
     */
    private static function next(string $set): string
    {
        if (empty(self::$unused[$set])) {
            $files = array_map(
                fn (string $path) => $set.'/'.basename($path),
                glob(self::SOURCE."/{$set}/*.*") ?: [],
            );
            shuffle($files);
            self::$unused[$set] = $files;
        }

        return array_pop(self::$unused[$set]);
    }

    private static function copy(string $source, string $folder): ?string
    {
        $file = self::SOURCE.'/'.$source;

        if (! is_file($file)) {
            return null;
        }

        $path = $folder.'/'.Str::random(40).'.'.pathinfo($file, PATHINFO_EXTENSION);
        Storage::disk('public')->put($path, (string) file_get_contents($file));

        return $path;
    }
}
