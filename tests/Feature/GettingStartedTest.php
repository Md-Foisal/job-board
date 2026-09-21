<?php

use App\Enums\MembershipRole;
use App\Models\Company;
use App\Models\User;

test('someone with neither side set up is offered both instead of a blank page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Start a candidate profile')
        ->assertSee('Create a company');
});

test('starting a candidate profile makes them a candidate, once', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('candidate.start'))
        ->assertRedirect(route('candidate.profile.edit'));

    expect($user->fresh()->isCandidate())->toBeTrue();

    $this->actingAs($user->fresh())
        ->post(route('candidate.start'))
        ->assertRedirect(route('candidate.dashboard'));

    expect($user->candidateProfile()->count())->toBe(1);
});

test('an employer can start a candidate side from their company workspace', function () {
    $company = Company::factory()->create();
    $employer = employerUser($company, MembershipRole::Owner);

    $this->actingAs($employer)
        ->get(route('employer.dashboard', $company))
        ->assertSee('Start a candidate profile')
        ->assertSee('Create a company');
});

test('a candidate who also works at a company can reach it from the candidate side', function () {
    $company = Company::factory()->create(['name' => 'Acme Hiring Ltd']);
    $user = candidateUser();
    App\Models\Membership::factory()->for($user)->for($company)->create(['role' => MembershipRole::Member]);

    $this->actingAs($user)
        ->get(route('candidate.dashboard'))
        ->assertSee('Acme Hiring Ltd')
        ->assertSee(route('employer.dashboard', $company))
        ->assertDontSee('Start a candidate profile')
        ->assertSee('Create a company');
});

test('guests are sent to sign in before starting anything', function () {
    $this->post(route('candidate.start'))->assertRedirect(route('login'));
});
