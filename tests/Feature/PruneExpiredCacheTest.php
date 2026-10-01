<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

test('expired entries in the database cache are deleted, and live ones kept', function () {
    config(['cache.default' => 'database']);

    Cache::put('ai-match:1:2:old', ['status' => 'done'], 60);
    Cache::put('ai-match:1:2:new', ['status' => 'done'], 3600);
    Cache::forever('settings', true);

    $this->travel(2)->minutes();

    $this->artisan('cache:prune-expired')
        ->expectsOutputToContain('Pruned 1 expired cache entry.')
        ->assertSuccessful();

    $stored = fn (string $key) => DB::table('cache')->where('key', 'like', "%{$key}")->exists();

    expect(DB::table('cache')->count())->toBe(2)
        ->and($stored('ai-match:1:2:old'))->toBeFalse()
        ->and($stored('ai-match:1:2:new'))->toBeTrue()
        ->and($stored('settings'))->toBeTrue();
});

test('a cache store that expires entries itself is left alone', function () {
    config(['cache.default' => 'array']);

    $this->artisan('cache:prune-expired')
        ->expectsOutputToContain('nothing to prune')
        ->assertSuccessful();
});

test('pruning runs every hour', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('cache:prune-expired')
        ->assertSuccessful();
});
