<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureActiveMembership;
use App\Http\Middleware\EnsureUserIsCandidate;
use App\Http\Middleware\EnsureUserIsEmployer;
use App\Http\Middleware\SyncTimezone;
use App\Support\LocalTime;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The one-click unsubscribe POST comes from a mail client, with no
        // session and so no token; its signed URL is what protects it.
        $middleware->preventRequestForgery(except: [
            'job-alerts/*/unsubscribe',
        ]);

        // Written by the browser's own script and holding nothing secret;
        // an encrypted cookie could not be written there at all.
        $middleware->encryptCookies(except: [
            LocalTime::COOKIE,
        ]);

        $middleware->web(append: [
            EnsureAccountIsActive::class,
            SyncTimezone::class,
        ]);

        $middleware->alias([
            'employer' => EnsureUserIsEmployer::class,
            'candidate' => EnsureUserIsCandidate::class,
            'company.member' => EnsureActiveMembership::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
