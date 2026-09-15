<?php

namespace App\Providers;

use App\Models\Category;
use App\Services\MatchScoreCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
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
        $this->configureViewComposers();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

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

    /**
     * The Categories dropdown in the shared Shell A navbar (partials/navbar.blade.php --
     * every guest page and every candidate account page) needs the category
     * list regardless of which controller/Livewire page or which layout
     * (guest vs the candidate sidebar shell) rendered it -- a composer keeps
     * that query out of every individual page.
     *
     * Matching on the view's compiled file path (rather than its dotted
     * name) is deliberate: the navbar partial is reached both through
     * <x-layouts::guest> (an anonymous-component tag Blade resolves under a
     * hashed namespace, not the literal "layouts::guest" string) and
     * through layouts/app/sidebar.blade.php -- a path-based match is the
     * one thing that stays true across both call sites.
     */
    protected function configureViewComposers(): void
    {
        View::composer('*', function ($view): void {
            if (str_ends_with($view->getPath(), 'partials'.DIRECTORY_SEPARATOR.'navbar.blade.php')) {
                $view->with('navCategories', Category::orderBy('name')->get());
            }
        });
    }
}
