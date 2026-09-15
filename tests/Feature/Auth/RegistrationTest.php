<?php

use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'candidate',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('registering as a candidate creates an empty candidate profile', function () {
    $this->post(route('register.store'), [
        'name' => 'Jane Candidate',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'candidate',
    ]);

    $user = User::where('email', 'jane@example.com')->firstOrFail();

    expect($user->candidateProfile)->not->toBeNull();
});

test('registering as an employer does not create a candidate profile', function () {
    $this->post(route('register.store'), [
        'name' => 'John Employer',
        'email' => 'john@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'employer',
    ]);

    $user = User::where('email', 'john@example.com')->firstOrFail();

    expect($user->candidateProfile)->toBeNull();
});
