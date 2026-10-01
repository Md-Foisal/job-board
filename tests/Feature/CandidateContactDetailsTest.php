<?php

test('a candidate saves a phone number and location, shown only on their own profile page', function () {
    $candidate = candidateUser()->fresh();

    $this->actingAs($candidate)
        ->patch(route('candidate.profile.update'), [
            'phone' => ' +880 1712-345678 ',
            'location' => 'Dhaka, Bangladesh',
        ])
        ->assertSessionHasNoErrors();

    $profile = $candidate->candidateProfile->refresh();

    expect($profile->phone)->toBe('+880 1712-345678')
        ->and($profile->location)->toBe('Dhaka, Bangladesh');

    $this->actingAs($candidate)
        ->get(route('candidate.profile.edit'))
        ->assertOk()
        ->assertSee('+880 1712-345678')
        ->assertSee("Shown on CVs you build here. Companies don't see these on your profile.");
});

test('emptying the fields removes them', function () {
    $candidate = candidateUser()->fresh();
    $candidate->candidateProfile->update(['phone' => '+880 1712-345678', 'location' => 'Dhaka']);

    $this->actingAs($candidate)
        ->patch(route('candidate.profile.update'), ['phone' => '', 'location' => ''])
        ->assertSessionHasNoErrors();

    $profile = $candidate->candidateProfile->refresh();

    expect($profile->phone)->toBeNull()
        ->and($profile->location)->toBeNull();
});

test('a phone number is accepted in the shapes people write it', function (string $phone) {
    $candidate = candidateUser()->fresh();

    $this->actingAs($candidate)
        ->patch(route('candidate.profile.update'), ['phone' => $phone])
        ->assertSessionHasNoErrors();

    expect($candidate->candidateProfile->refresh()->phone)->toBe($phone);
})->with([
    '+880 1712-345678',
    '(+44) 20 7946 0000',
    '+1 (555) 010-9999',
    '030/1234567',
    '01712.345.678',
]);

test('a phone number or location the profile cannot hold is refused, and nothing is saved', function (array $input, string $field) {
    $candidate = candidateUser()->fresh();

    $this->actingAs($candidate)
        ->from(route('candidate.profile.edit'))
        ->patch(route('candidate.profile.update'), $input)
        ->assertRedirect(route('candidate.profile.edit'))
        ->assertSessionHasErrors($field);

    $profile = $candidate->candidateProfile->refresh();

    expect($profile->phone)->toBeNull()
        ->and($profile->location)->toBeNull();
})->with([
    'letters' => [['phone' => 'call me'], 'phone'],
    'too few digits' => [['phone' => '1234'], 'phone'],
    'too many digits' => [['phone' => '123456789012345678901'], 'phone'],
    'a plus in the middle' => [['phone' => '880+1712345678'], 'phone'],
    'longer than the field' => [['phone' => '+880 1 7 1 2 3 4 5 6 7 8 9 0 1 2'], 'phone'],
    'a location too long' => [['location' => str_repeat('a', 101)], 'location'],
]);

test('a refused phone number reopens its section with the message and what was typed', function () {
    $candidate = candidateUser()->fresh();

    $this->actingAs($candidate)
        ->from(route('candidate.profile.edit'))
        ->patch(route('candidate.profile.update'), ['phone' => 'call me'])
        ->assertRedirect();

    $this->actingAs($candidate)
        ->get(route('candidate.profile.edit'))
        ->assertSee('editingContact: true', false)
        ->assertSee('Enter a phone number using digits, with an optional + and country code.')
        ->assertSee('value="call me"', false);
});

test('phone and location do not count towards profile completion', function () {
    $candidate = candidateUser()->fresh();
    $before = $this->actingAs($candidate)->get(route('candidate.dashboard'))->viewData('profileCompletionPercent');

    $candidate->candidateProfile->update(['phone' => '+880 1712-345678', 'location' => 'Dhaka']);

    expect($this->actingAs($candidate)->get(route('candidate.dashboard'))->viewData('profileCompletionPercent'))->toBe($before);
});
