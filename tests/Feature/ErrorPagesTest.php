<?php

use App\Models\Company;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('web')->get('/_status/{code}', function (int $code) {
        abort($code, request()->query('reason', ''));
    });
});

test('an unknown address gets the site’s own 404, with a way back to the jobs', function () {
    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertSee('We can’t find that page')
        ->assertSee(route('jobs.index'), false)
        ->assertSee('Error 404');
});

test('an unknown address is answered inside the web middleware, so the navbar knows who is signed in', function () {
    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertCookie(config('session.cookie'));
});

test('a missing record’s 404 does not name the model behind it', function () {
    $this->get(route('companies.show', 'no-such-company'))
        ->assertNotFound()
        ->assertDontSee('App\\Models\\'.class_basename(Company::class), false)
        ->assertDontSee('No query results');
});

// Livewire only injects its assets into 200 responses, so every error
// page has to load them itself.
test('each error has its own heading in the site’s frame', function (int $code, string $heading) {
    $this->get('/_status/'.$code)
        ->assertStatus($code)
        ->assertSee($heading)
        ->assertSee('Error '.$code)
        ->assertSee('livewire.js', false);
})->with([
    [403, 'You don’t have access to this page'],
    [404, 'We can’t find that page'],
    [413, 'That upload was too large'],
    [419, 'This page timed out'],
    [429, 'Too many tries'],
    [500, 'Something went wrong on our side'],
    [503, 'We’ll be back shortly'],
    [405, 'That didn’t work'],
    [502, 'Something went wrong on our side'],
    [401, 'Unauthorized'],
]);

test('a 403 shows the reason it was given, but not Laravel’s empty default', function () {
    $this->get('/_status/403?reason='.urlencode('Two-factor authentication is required for staff accounts.'))
        ->assertSee('Two-factor authentication is required for staff accounts.');

    $this->get('/_status/403?reason='.urlencode('This action is unauthorized.'))
        ->assertDontSee('This action is unauthorized.')
        ->assertSee('This page is for a different kind of account');
});

test('an employer on a candidate page is told how to get the candidate side', function () {
    $this->actingAs(employerUser())
        ->get(route('candidate.dashboard'))
        ->assertForbidden()
        ->assertSee('This page is for job seekers.');
});

test('an unknown address is a 404 whatever the method, not a 405', function () {
    $this->post('/no-such-page')->assertNotFound();
});
