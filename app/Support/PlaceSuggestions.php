<?php

namespace App\Support;

use App\Enums\WorkplaceType;
use App\Models\JobPosting;

/**
 * What the "where" box offers as it is typed into: Remote, and the cities
 * that have open jobs, each with how many. The large boards suggest
 * places the same way, so a visitor picks one the search will find
 * instead of guessing at a spelling. Only places with open jobs are
 * offered; one without would lead to an empty page.
 */
final class PlaceSuggestions
{
    public const LIMIT = 6;

    /**
     * Places whose name starts with the text, or has a word that does
     * ("york" finds New York); before anything is typed, the places with
     * the most jobs.
     *
     * @return list<array{value: string, label: string, jobs: int, remote: bool}>
     */
    public static function for(string $typed): array
    {
        $typed = mb_strtolower(trim($typed));

        $matches = array_filter(self::all(), function (array $place) use ($typed) {
            if ($typed === '') {
                return true;
            }

            $name = mb_strtolower($place['label']);

            return str_starts_with($name, $typed) || str_contains($name, ' '.$typed);
        });

        return array_slice(array_values($matches), 0, self::LIMIT);
    }

    /**
     * Remote first, then cities by number of open jobs.
     *
     * @return list<array{value: string, label: string, jobs: int, remote: bool}>
     */
    private static function all(): array
    {
        return PublicCache::remember('place-suggestions', function () {
            $remote = JobPosting::query()
                ->active()
                ->where('workplace_type', WorkplaceType::Remote->value)
                ->count();

            $cities = JobPosting::query()
                ->active()
                ->whereNotNull('location_city')
                ->where('location_city', '!=', '')
                ->selectRaw('location_city, count(*) as jobs')
                ->groupBy('location_city')
                ->orderByDesc('jobs')
                ->orderBy('location_city')
                ->toBase()
                ->get()
                ->map(fn (object $row) => [
                    'value' => $row->location_city,
                    'label' => $row->location_city,
                    'jobs' => (int) $row->jobs,
                    'remote' => false,
                ])
                ->all();

            return array_values(array_filter([
                $remote > 0 ? ['value' => WorkplaceType::Remote->label(), 'label' => WorkplaceType::Remote->label(), 'jobs' => $remote, 'remote' => true] : null,
                ...$cities,
            ]));
        });
    }
}
