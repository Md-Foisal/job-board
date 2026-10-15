<?php

test('the pay asked for is plainly monthly, with the currency first and the year worked out beside it', function () {
    $this->actingAs(candidateUser())
        ->get(route('candidate.preferences.edit'))
        ->assertOk()
        ->assertSeeInOrder(['Currency', 'Pay you want, per month', 'At least, per month', 'Up to, per month'])
        ->assertSee('x-text="yearly"', false)
        ->assertSee('a year.');
});

test('the page is marked private and does not claim that jobs are compared with the date the candidate is free', function () {
    $this->actingAs(candidateUser())
        ->get(route('candidate.preferences.edit'))
        ->assertSee('Private')
        ->assertSee('Only you see these. Each job page compares its pay, workplace, location and type of work with them.')
        ->assertSee('For your own planning; jobs are not compared with it.');
});

test('where the candidate lives is asked as a country from the list, a city and whether they would move', function () {
    $this->actingAs(candidateUser())
        ->get(route('candidate.preferences.edit'))
        ->assertSeeInOrder(['Where you live', 'Country', 'Bangladesh', 'City', 'I would move to another city for the right job']);
});

test('a country outside the list is refused, and moving is off unless ticked', function () {
    $user = candidateUser();

    $this->actingAs($user)
        ->patch(route('candidate.preferences.update'), ['location_country' => 'Bangladesh'])
        ->assertSessionHasErrors('location_country');

    $this->actingAs($user)
        ->patch(route('candidate.preferences.update'), ['location_country' => 'BD', 'location_city' => 'Sylhet', 'open_to_relocation' => '1'])
        ->assertSessionHasNoErrors();

    expect($user->candidateProfile->preference()->first())
        ->location_country->toBe('BD')
        ->location_city->toBe('Sylhet')
        ->open_to_relocation->toBeTrue();

    $this->actingAs($user)
        ->patch(route('candidate.preferences.update'), ['location_country' => 'BD', 'location_city' => 'Sylhet'])
        ->assertSessionHasNoErrors();

    expect($user->candidateProfile->preference()->first()->open_to_relocation)->toBeFalse();
});
