<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Report;
use App\Models\Skill;
use App\Observers\FlushLookupCache;
use App\Observers\FlushPublicCache;
use App\Services\MatchScoreCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MatchScoreCalculator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerCacheFlushing();
    }

    /**
     * What keeps App\Support\PublicCache honest: the models whose changes
     * alter what visitors see, each moving the matching generation on.
     */
    protected function registerCacheFlushing(): void
    {
        JobPosting::observe(FlushPublicCache::class);
        Company::observe(FlushPublicCache::class);
        Report::observe(FlushPublicCache::class);

        Skill::observe(FlushLookupCache::class);
        Category::observe(FlushLookupCache::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Outside production, turn Eloquent's two silent-wrong-data behaviors
        // into loud ones: assigning an attribute that is not fillable, and
        // reading a column that was never loaded because the query selected a
        // subset. Both otherwise fail by quietly producing null, which reads
        // as real data everywhere downstream.
        //
        // Lazy loading is deliberately left enabled: it is a query-count
        // concern, not a correctness one, and turning it off here would make
        // every policy that reaches for its record's parent throw.
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
        Model::preventAccessingMissingAttributes(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
