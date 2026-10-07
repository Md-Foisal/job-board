<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login', absolute: false));

        return true;
    });
});

test('asking for a reset link says the same thing whether or not the address has an account', function () {
    Notification::fake();

    $user = User::factory()->create();
    $message = 'If an account uses that email address, we have sent it a link to reset the password. It can take a minute to arrive.';

    $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => $user->email])
        ->assertRedirect(route('password.request'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', $message);

    $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => 'nobody@example.com'])
        ->assertRedirect(route('password.request'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', $message);

    Notification::assertSentTimes(ResetPassword::class, 1);
});

test('the reset form asks for the new password once', function () {
    $this->get(route('password.reset', ['token' => 'token', 'email' => 'rafi@example.com']))
        ->assertOk()
        ->assertSee('Choose a new password')
        ->assertDontSeeHtml('name="password_confirmation"');
});
