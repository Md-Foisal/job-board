<?php

namespace App\Providers;

use App\Models\Category;
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
        //
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
     * The Categories dropdown in the guest/public navbar (Shell A, every
     * browsing page) needs the category list regardless of which
     * controller or Livewire page rendered that layout -- a composer
     * keeps that query out of every individual page.
     */
    protected function configureViewComposers(): void
    {
        View::composer('layouts::guest', function ($view): void {
            $view->with('navCategories', Category::orderBy('name')->get());
        });
    }
}
