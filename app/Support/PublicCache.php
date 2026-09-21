<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use LogicException;

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
 *
 * Only plain data goes into the cache: strings, numbers, arrays. Laravel
 * refuses to unserialize any class from a cache store (the
 * cache.serializable_classes default), so that a leaked APP_KEY cannot be
 * turned into code execution through a forged entry. Models are therefore
 * stored as their raw attributes and rebuilt on the way out (models(),
 * lookupModels()), and remember() rejects anything else outright.
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

    /**
     * Plain data only -- see the class comment.
     */
    public static function remember(string $key, Closure $callback): mixed
    {
        return Cache::remember(self::key(self::PUBLIC, $key), self::TTL_SECONDS, fn () => self::plain($callback()));
    }

    /**
     * A list of models, cached as their attributes and those of the given
     * single-model relations (a posting's company), and rebuilt as models
     * on every read.
     *
     * @param  class-string<Model>  $model
     * @param  Closure(): Collection  $query
     * @param  list<string>  $relations
     */
    public static function models(string $key, string $model, Closure $query, array $relations = []): Collection
    {
        return self::rebuild($model, $relations, Cache::remember(
            self::key(self::PUBLIC, $key), self::TTL_SECONDS, fn () => self::flatten($query(), $relations),
        ));
    }

    /**
     * The skill and category lists, on their own slower generation.
     *
     * @param  class-string<Model>  $model
     * @param  Closure(): Collection  $query
     */
    public static function lookupModels(string $key, string $model, Closure $query): Collection
    {
        return self::rebuild($model, [], Cache::remember(
            self::key(self::LOOKUPS, $key), self::TTL_SECONDS, fn () => self::flatten($query(), []),
        ));
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

    /**
     * @return list<array{attributes: array<string, mixed>, relations: array<string, ?array<string, mixed>>}>
     */
    private static function flatten(Collection $models, array $relations): array
    {
        return $models->map(fn (Model $model) => [
            'attributes' => $model->getAttributes(),
            'relations' => collect($relations)
                ->mapWithKeys(fn (string $relation) => [$relation => $model->getRelation($relation)?->getAttributes()])
                ->all(),
        ])->values()->all();
    }

    /**
     * @param  class-string<Model>  $model
     */
    private static function rebuild(string $model, array $relations, array $rows): Collection
    {
        $prototype = new $model;
        // Named explicitly, as a query would name it: a rebuilt model with no
        // connection name would not count as the same record (is()) as one
        // read from the database.
        $connection = $prototype->getConnection()->getName();

        return $prototype->newCollection(array_map(function (array $row) use ($prototype, $relations, $connection) {
            $instance = $prototype->newFromBuilder($row['attributes'], $connection);

            foreach ($relations as $relation) {
                $attributes = $row['relations'][$relation] ?? null;
                $related = $prototype->{$relation}()->getRelated();
                $instance->setRelation($relation, $attributes === null
                    ? null
                    : $related->newFromBuilder($attributes, $related->getConnection()->getName()));
            }

            return $instance;
        }, $rows));
    }

    private static function plain(mixed $value): mixed
    {
        if (is_object($value)) {
            throw new LogicException('PublicCache stores plain data only; got '.$value::class.'. Use models() for models.');
        }

        if (is_array($value)) {
            array_walk_recursive($value, fn ($item) => self::plain($item));
        }

        return $value;
    }
}
