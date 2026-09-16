<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // No ->login(): staff sign in through the application's own
            // login route. A second sign-in screen would bypass Fortify,
            // and with it the two-factor challenge that staff accounts
            // are required to pass.
            ->colors([
                'primary' => [
                    50 => 'oklch(0.98 0.014 175)',
                    100 => 'oklch(0.95 0.028 175)',
                    200 => 'oklch(0.90 0.045 175)',
                    300 => 'oklch(0.82 0.065 175)',
                    400 => 'oklch(0.72 0.09 175)',
                    500 => 'oklch(0.62 0.11 175)',
                    600 => 'oklch(0.52 0.115 175)',
                    700 => 'oklch(0.43 0.10 175)',
                    800 => 'oklch(0.34 0.075 175)',
                    900 => 'oklch(0.27 0.05 175)',
                    950 => 'oklch(0.18 0.03 175)',
                ],
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
