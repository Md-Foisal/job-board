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
use App\Support\DateFormat;
use App\Support\LocalTime;
use App\Support\PasswordPolicy;
use Carbon\CarbonImmutable;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Table;
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

        // The staff panel shows times in the staff member's own zone, as
        // the rest of the product does. A closure, because which person is
        // signed in is not known yet while providers boot.
        FilamentTimezone::set(fn (): string => LocalTime::zone());

        // And writes its dates the way the rest of the product does.
        Table::configureUsing(function (Table $table): void {
            $table
                ->defaultDateDisplayFormat(DateFormat::DAY)
                ->defaultDateTimeDisplayFormat(DateFormat::MOMENT);
        });

        Schema::configureUsing(function (Schema $schema): void {
            $schema
                ->defaultDateDisplayFormat(DateFormat::DAY)
                ->defaultDateTimeDisplayFormat(DateFormat::MOMENT);
        });

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

        Password::defaults(fn (): Password => PasswordPolicy::rule());
    }
}
