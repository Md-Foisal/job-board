<?php

namespace App\Providers\Filament;

use App\Http\Middleware\EnsureStaffHasTwoFactor;
use App\Http\Middleware\LoadStaffMemberships;
use App\Http\Middleware\SyncTimezone;
use App\Http\Responses\AdminLogoutResponse;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        // In boot, after every register(): Filament binds its own response
        // while registering, and the later binding is the one used.
        $this->app->bind(LogoutResponse::class, AdminLogoutResponse::class);
    }

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
            // Same typeface as the rest of the application, from the same
            // font host, so moving between the two does not change voice.
            ->font('Geist')
            // Looks and behaves like the rest of the product: the app's
            // wordmark, which leads back to the public site as it does
            // everywhere else; one light/dark setting shared with the app
            // (its switch in the bar, Filament's own three-way one removed so
            // there are not two that disagree); and the app's surfaces.
            // See resources/views/filament/.
            ->brandName('JobBoard')
            ->brandLogo(fn () => view('filament.brand'))
            ->homeUrl(fn () => route('home'))
            ->themeSwitcher(false)
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => view('filament.shell-head'))
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn () => view('filament.theme-toggle'))
            ->userMenuItems([
                'site' => Action::make('site')
                    ->label('View site')
                    ->icon(Heroicon::OutlinedGlobeAlt)
                    ->url(fn () => route('home')),
            ])
            // The application's brand and neutral scales (resources/css/app.css),
            // in oklch because Filament works out readable text colours for
            // each shade from these values.
            ->colors([
                'primary' => [
                    50 => 'oklch(0.980 0.016 73.7)',
                    100 => 'oklch(0.954 0.037 75.2)',
                    200 => 'oklch(0.901 0.073 70.7)',
                    300 => 'oklch(0.837 0.117 66.3)',
                    400 => 'oklch(0.758 0.159 55.9)',
                    500 => 'oklch(0.646 0.194 41.1)',
                    600 => 'oklch(0.553 0.174 38.4)',
                    700 => 'oklch(0.470 0.143 37.3)',
                    800 => 'oklch(0.408 0.116 38.2)',
                    900 => 'oklch(0.334 0.092 38.3)',
                    950 => 'oklch(0.266 0.076 36.3)',
                ],
                'gray' => [
                    50 => 'oklch(0.985 0 0)',
                    100 => 'oklch(0.967 0.001 286.4)',
                    200 => 'oklch(0.937 0 0)',
                    300 => 'oklch(0.871 0.005 286.3)',
                    400 => 'oklch(0.712 0.013 286.1)',
                    500 => 'oklch(0.552 0.014 285.9)',
                    600 => 'oklch(0.488 0.011 286.0)',
                    700 => 'oklch(0.370 0.012 285.8)',
                    800 => 'oklch(0.258 0.007 285.9)',
                    900 => 'oklch(0.183 0.004 286.0)',
                    950 => 'oklch(0.145 0.002 286.1)',
                ],
            ])
            // Named once, in this order, so a new group cannot jump the
            // queue: a group whose resources set no sort would otherwise
            // land at the top.
            ->navigationGroups(['Users & companies', 'Moderation', 'Support', 'Master data'])
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
            // Persistent, so the checks also run on every Livewire request a
            // panel page makes -- which is how every table action is sent.
            // The panel has its own stack rather than the web group, so the
            // time zone sync is listed here as well.
            ->authMiddleware([
                Authenticate::class,
                EnsureStaffHasTwoFactor::class,
                LoadStaffMemberships::class,
                SyncTimezone::class,
            ], isPersistent: true);
    }
}
