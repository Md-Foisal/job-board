<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureActiveMembership;
use App\Http\Middleware\EnsureUserIsCandidate;
use App\Http\Middleware\EnsureUserIsEmployer;
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

        $middleware->web(append: [
            EnsureAccountIsActive::class,
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
