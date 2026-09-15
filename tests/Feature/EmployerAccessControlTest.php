<?php

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;

/**
 * The guard is exercised through a real company-scoped page rather than a
 * throwaway route: what matters is that the actual protected surface is
 * protected, not that the middleware works in isolation.
 */
test('an active member reaches a company-scoped page', function () {
    $company = Company::factory()->create();

    $this->actingAs(employerUser($company))
        ->get(route('employer.dashboard', $company))
        ->assertOk();
});

test('someone from another company is turned away', function () {
    $company = Company::factory()->create();

    $this->actingAs(employerUser())
        ->get(route('employer.dashboard', $company))
        ->assertForbidden();
});

test('a candidate with no membership anywhere is turned away', function () {
    $company = Company::factory()->create();

    $this->actingAs(candidateUser())
        ->get(route('employer.dashboard', $company))
        ->assertForbidden();
});

test('deactivating a membership closes the door on the next request', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $membership = Membership::factory()->for($user)->for($company)->create();

    $this->actingAs($user)
        ->get(route('employer.dashboard', $company))
        ->assertOk();

    $membership->update(['status' => MembershipStatus::Inactive]);

    $this->actingAs($user)
        ->get(route('employer.dashboard', $company))
        ->assertForbidden();
});

test('a guest is sent to log in rather than refused', function () {
    $company = Company::factory()->create();

    $this->get(route('employer.dashboard', $company))
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

    // Someone else's row, so a refusal here is about authority rather
    // than about the separate rule against editing your own standing.
    $thirdPerson = employerUser($company, MembershipRole::Member);
    $theirRow = $thirdPerson->memberships()->first();

    expect($manager->can('update', $theirRow))->toBeTrue();
    expect($manager->can('deactivate', $theirRow))->toBeTrue();
    expect($member->can('update', $theirRow))->toBeFalse();
});

test('a manager cannot touch an owner', function () {
    $company = Company::factory()->create();
    $manager = employerUser($company, MembershipRole::Manager);
    $owner = employerUser($company, MembershipRole::Owner);
    $ownersRow = $owner->memberships()->first();

    expect($manager->can('update', $ownersRow))->toBeFalse();
    expect($manager->can('deactivate', $ownersRow))->toBeFalse();
});

test('an owner may act on another owner', function () {
    $company = Company::factory()->create();
    $first = employerUser($company, MembershipRole::Owner);
    $second = employerUser($company, MembershipRole::Owner);

    expect($first->can('update', $second->memberships()->first()))->toBeTrue();
});

test('nobody may change their own standing on the team', function () {
    $company = Company::factory()->create();
    $manager = employerUser($company, MembershipRole::Manager);
    // A second owner exists, so the continuity rule is not what is doing
    // the refusing here -- the self rule is.
    $owner = employerUser($company, MembershipRole::Owner);
    employerUser($company, MembershipRole::Owner);

    expect($manager->can('update', $manager->memberships()->first()))->toBeFalse();
    expect($owner->can('update', $owner->memberships()->first()))->toBeFalse();
});

test('a company knows when it is down to its last owner', function () {
    $company = Company::factory()->create();
    $first = employerUser($company, MembershipRole::Owner);
    $second = employerUser($company, MembershipRole::Owner);

    $firstRow = $first->memberships()->first();

    expect($firstRow->isLastActiveOwner())->toBeFalse();

    $second->memberships()->first()->update(['role' => MembershipRole::Manager]);

    expect($firstRow->fresh()->isLastActiveOwner())->toBeTrue();
});

test('a manager and a plain member are never the last owner', function () {
    $company = Company::factory()->create();
    employerUser($company, MembershipRole::Owner);
    $manager = employerUser($company, MembershipRole::Manager);

    expect($manager->memberships()->first()->isLastActiveOwner())->toBeFalse();
});

test('an ended membership does not count towards ownership continuity', function () {
    $company = Company::factory()->create();
    $staying = employerUser($company, MembershipRole::Owner);
    $left = employerUser($company, MembershipRole::Owner);

    $left->memberships()->first()->update(['status' => MembershipStatus::Inactive]);

    // On paper there are two owner rows; only one of them still counts.
    expect($staying->memberships()->first()->isLastActiveOwner())->toBeTrue();
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
