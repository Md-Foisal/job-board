<?php

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * The middleware has no page of its own, so the tests below hang it on a
 * throwaway route -- what is under test is the guard, not any screen.
 */
beforeEach(function () {
    Route::middleware(['web', 'auth', 'company.member'])
        ->get('/__test__/companies/{company:slug}', fn () => 'reached')
        ->name('test.company.scoped');
});

test('an active member reaches a company-scoped page', function () {
    $company = Company::factory()->create();
    $member = employerUser($company);

    $this->actingAs($member)
        ->get(route('test.company.scoped', $company))
        ->assertOk()
        ->assertSee('reached');
});

test('someone from another company is turned away', function () {
    $company = Company::factory()->create();
    $outsider = employerUser();

    $this->actingAs($outsider)
        ->get(route('test.company.scoped', $company))
        ->assertForbidden();
});

test('a candidate with no membership anywhere is turned away', function () {
    $company = Company::factory()->create();

    $this->actingAs(candidateUser())
        ->get(route('test.company.scoped', $company))
        ->assertForbidden();
});

test('deactivating a membership closes the door on the next request', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $membership = Membership::factory()->for($user)->for($company)->create();

    $this->actingAs($user)
        ->get(route('test.company.scoped', $company))
        ->assertOk();

    $membership->update(['status' => MembershipStatus::Inactive]);

    $this->actingAs($user)
        ->get(route('test.company.scoped', $company))
        ->assertForbidden();
});

test('a guest is sent to log in rather than refused', function () {
    $company = Company::factory()->create();

    $this->get(route('test.company.scoped', $company))
        ->assertRedirect(route('login'));
});

test('editing the company profile is owner and manager only', function () {
    $company = Company::factory()->create();

    expect(employerUser($company, MembershipRole::Owner)->can('update', $company))->toBeTrue();
    expect(employerUser($company, MembershipRole::Manager)->can('update', $company))->toBeTrue();
    expect(employerUser($company, MembershipRole::Member)->can('update', $company))->toBeFalse();
    expect(employerUser()->can('update', $company))->toBeFalse();
});

test('the team roster is owner and manager only', function () {
    $company = Company::factory()->create();
    $member = employerUser($company, MembershipRole::Member);
    $manager = employerUser($company, MembershipRole::Manager);

    expect($manager->can('viewAny', [Membership::class, $company]))->toBeTrue();
    expect($member->can('viewAny', [Membership::class, $company]))->toBeFalse();

    $someonesMembership = $member->memberships()->first();

    expect($manager->can('update', $someonesMembership))->toBeTrue();
    expect($manager->can('deactivate', $someonesMembership))->toBeTrue();
    expect($member->can('update', $someonesMembership))->toBeFalse();
});

test('inviting and revoking is owner and manager only', function () {
    $company = Company::factory()->create();
    $manager = employerUser($company, MembershipRole::Manager);
    $member = employerUser($company, MembershipRole::Member);

    expect($manager->can('create', [Invitation::class, $company]))->toBeTrue();
    expect($member->can('create', [Invitation::class, $company]))->toBeFalse();

    $invitation = Invitation::factory()->for($company)->create();

    expect($manager->can('revoke', $invitation))->toBeTrue();
    expect($member->can('revoke', $invitation))->toBeFalse();
});
