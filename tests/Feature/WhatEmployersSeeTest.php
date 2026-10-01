<?php

use App\Enums\WorkplaceType;

test('the preference form makes no promise about what employers see', function () {
    $this->actingAs(candidateUser())
        ->get(route('candidate.preferences.edit'))
        ->assertOk()
        ->assertSee('Only you see them.')
        ->assertDontSee('Actively searching')
        ->assertDontSee('Shows employers');
});

test('a candidate saves their preferences', function () {
    $user = candidateUser();

    $this->actingAs($user)
        ->patch(route('candidate.preferences.update'), [
            'desired_salary_min' => 3000,
            'desired_salary_max' => 4500,
            'desired_salary_currency' => 'EUR',
            'preferred_workplace_type' => WorkplaceType::Remote->value,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $preference = $user->candidateProfile->preference()->first();

    expect($preference->desired_salary_min)->toBe(3000)
        ->and($preference->desired_salary_max)->toBe(4500)
        ->and($preference->desired_salary_currency)->toBe('EUR')
        ->and($preference->preferred_workplace_type)->toBe(WorkplaceType::Remote);
});

test('the profile page says exactly what a company sees', function () {
    // fresh(): the page reads avatar, which a factory-made user does not
    // carry until it is loaded from the database.
    $this->actingAs(candidateUser()->fresh())
        ->get(route('candidate.profile.edit'))
        ->assertOk()
        ->assertSee('When you apply, the company sees your name, headline, bio and skills, with the CV you attach.')
        ->assertDontSee('look you up');
});
