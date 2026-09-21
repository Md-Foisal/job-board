<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caching for what every visitor sees the same way: the homepage, the
 * sitemap, the platform numbers on the admin dashboard, and the skill and
 * category lists every filter and form draws from.
 *
 * Invalidation works by generation rather than by tags. Laravel's cache
 * tags are not available on the database or file stores, which is what
 * this app runs on, so every key carries a generation number and flushing
 * means moving to the next one; the old entries are simply never read
 * again and expire on their own. It works the same on every store.
 *
 * There are two generations, because they change at very different rates:
 * "public" moves whenever a posting, a company or a report changes, since
 * any of those can change what a visitor is shown; "lookups" moves only
 * when a skill or category is added, renamed, merged or removed, which
 * happens a few times a month.
 *
 * What is deliberately not cached: whole pages, because every page carries
 * the visitor's own CSRF token and a cached one would hand it to the next
 * visitor; and the job search results, whose seven filters, three sort
 * orders and pages make so many combinations that almost no entry would
 * ever be read twice -- that query is served by its indexes instead.
 */
final class PublicCache
{
    /**
     * A safety net only. Changes flush sooner through the generation;
     * this bounds how long anything missed by that could stay stale, such
     * as a posting passing its closing date without anyone saving it.
     */
    public const TTL_SECONDS = 600;

    private const PUBLIC = 'public';

    private const LOOKUPS = 'lookups';

    public static function remember(string $key, Closure $callback): mixed
    {
        return Cache::remember(self::key(self::PUBLIC, $key), self::TTL_SECONDS, $callback);
    }

    public static function lookup(string $key, Closure $callback): mixed
    {
        return Cache::remember(self::key(self::LOOKUPS, $key), self::TTL_SECONDS, $callback);
    }

    public static function flushPublic(): void
    {
        self::advance(self::PUBLIC);
    }

    /**
     * A renamed or merged category also changes the homepage, so lookups
     * never move alone.
     */
    public static function flushLookups(): void
    {
        self::advance(self::LOOKUPS);
        self::advance(self::PUBLIC);
    }

    private static function key(string $generation, string $key): string
    {
        return "{$generation}:".self::generation($generation).":{$key}";
    }

    private static function generation(string $name): int
    {
        return (int) Cache::rememberForever("cache-generation:{$name}", fn () => 1);
    }

    private static function advance(string $name): void
    {
        // Seed first: incrementing a key that does not exist yet fails
        // quietly on some stores.
        Cache::add("cache-generation:{$name}", 1);
        Cache::increment("cache-generation:{$name}");
    }
}
