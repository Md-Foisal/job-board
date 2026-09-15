<?php

use App\Enums\InvitationStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\TeamMemberInvited;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('a plain member cannot open the roster', function () {
    $company = Company::factory()->create();

    $this->actingAs(employerUser($company, MembershipRole::Member))
        ->get(route('employer.team.index', $company))
        ->assertForbidden();
});

test('a manager sees the roster', function () {
    $company = Company::factory()->create();
    $manager = employerUser($company, MembershipRole::Manager);
    $colleague = employerUser($company, MembershipRole::Member);

    $this->actingAs($manager)
        ->get(route('employer.team.index', $company))
        ->assertOk()
        ->assertSee($colleague->name);
});

test('inviting someone records it and emails the address', function () {
    Notification::fake();

    $company = Company::factory()->create();
    $owner = employerUser($company, MembershipRole::Owner);

    Livewire::actingAs($owner)
        ->test('pages::employer.team', ['company' => $company])
        ->set('inviteEmail', 'Nadia@Example.com')
        ->set('inviteRole', 'manager')
        ->call('invite')
        ->assertHasNoErrors();

    $invitation = $company->invitations()->sole();

    // Stored lowercase so the address match at accept time is not defeated
    // by however the sender happened to type it.
    expect($invitation->email)->toBe('nadia@example.com');
    expect($invitation->role)->toBe(MembershipRole::Manager);
    expect($invitation->status)->toBe(InvitationStatus::Pending);

    Notification::assertSentOnDemand(TeamMemberInvited::class);
});

test('the same address cannot be invited twice while one is still open', function () {
    Notification::fake();

    $company = Company::factory()->create();
    $owner = employerUser($company, MembershipRole::Owner);
    Invitation::factory()->for($company)->create(['email' => 'nadia@example.com']);

    Livewire::actingAs($owner)
        ->test('pages::employer.team', ['company' => $company])
        ->set('inviteEmail', 'nadia@example.com')
        ->call('invite')
        ->assertHasErrors('inviteEmail');

    expect($company->invitations()->count())->toBe(1);
});

test('revoking an invitation takes the link out of use', function () {
    $company = Company::factory()->create();
    $owner = employerUser($company, MembershipRole::Owner);
    $invitation = Invitation::factory()->for($company)->create();

    Livewire::actingAs($owner)
        ->test('pages::employer.team', ['company' => $company])
        ->call('revokeInvitation', $invitation->id);

    expect($invitation->fresh()->status)->toBe(InvitationStatus::Revoked);

    $this->get(route('invitations.show', $invitation->token))->assertNotFound();
});

test('removing a member ends the membership instead of deleting it', function () {
    $company = Company::factory()->create();
    $owner = employerUser($company, MembershipRole::Owner);
    $member = employerUser($company, MembershipRole::Member);
    $membership = $member->memberships()->sole();

    Livewire::actingAs($owner)
        ->test('pages::employer.team', ['company' => $company])
        ->call('removeMember', $membership->id);

    expect($membership->fresh()->status)->toBe(MembershipStatus::Inactive);
    expect(Membership::find($membership->id))->not->toBeNull();

    $this->actingAs($member)
        ->get(route('employer.dashboard', $company))
        ->assertForbidden();
});

test('a manager cannot remove an owner through the roster', function () {
    $company = Company::factory()->create();
    $manager = employerUser($company, MembershipRole::Manager);
    $owner = employerUser($company, MembershipRole::Owner);
    $ownersRow = $owner->memberships()->sole();

    Livewire::actingAs($manager)
        ->test('pages::employer.team', ['company' => $company])
        ->call('removeMember', $ownersRow->id)
        ->assertForbidden();

    expect($ownersRow->fresh()->status)->toBe(MembershipStatus::Active);
});

test('an invitation cannot be accepted by whoever happens to hold the link', function () {
    $company = Company::factory()->create();
    $invitation = Invitation::factory()->for($company)->create(['email' => 'nadia@example.com']);
    $someoneElse = User::factory()->create(['email' => 'stranger@example.com']);

    $this->actingAs($someoneElse)
        ->get(route('invitations.show', $invitation->token))
        ->assertOk()
        ->assertSee('nadia@example.com');

    $this->actingAs($someoneElse)->post(route('invitations.accept', $invitation->token));

    expect($company->memberships()->where('user_id', $someoneElse->id)->exists())->toBeFalse();
    expect($invitation->fresh()->status)->toBe(InvitationStatus::Pending);
});

test('the invited person joins and lands in the workspace', function () {
    $company = Company::factory()->create();
    $invitation = Invitation::factory()->for($company)->create([
        'email' => 'nadia@example.com',
        'role' => MembershipRole::Manager,
    ]);
    $nadia = User::factory()->create(['email' => 'nadia@example.com']);

    $this->actingAs($nadia)
        ->post(route('invitations.accept', $invitation->token))
        ->assertRedirect(route('employer.dashboard', $company));

    $membership = $company->memberships()->where('user_id', $nadia->id)->sole();
    expect($membership->role)->toBe(MembershipRole::Manager);
    expect($membership->status)->toBe(MembershipStatus::Active);
    expect($invitation->fresh()->status)->toBe(InvitationStatus::Accepted);
});

test('the same link cannot be used a second time', function () {
    $company = Company::factory()->create();
    $invitation = Invitation::factory()->for($company)->create(['email' => 'nadia@example.com']);
    $nadia = User::factory()->create(['email' => 'nadia@example.com']);

    $this->actingAs($nadia)->post(route('invitations.accept', $invitation->token));

    $this->actingAs($nadia)
        ->get(route('invitations.show', $invitation->token))
        ->assertNotFound();
});

test('an expired invitation is gone', function () {
    $invitation = Invitation::factory()->stale()->create(['email' => 'nadia@example.com']);

    $this->get(route('invitations.show', $invitation->token))->assertNotFound();
});

test('a guest is sent to sign in and brought back to the invitation', function () {
    $invitation = Invitation::factory()->create();

    $this->post(route('invitations.accept', $invitation->token))
        ->assertRedirect(route('login'));

    expect(session('url.intended'))->toBe(route('invitations.show', $invitation->token));
});

test('someone who left and is invited back reuses their old row', function () {
    $company = Company::factory()->create();
    $returning = User::factory()->create(['email' => 'rafiq@example.com']);
    $membership = Membership::factory()->for($returning)->for($company)->create();
    $membership->update(['status' => MembershipStatus::Inactive]);

    $invitation = Invitation::factory()->for($company)->create([
        'email' => 'rafiq@example.com',
        'role' => MembershipRole::Member,
    ]);

    $this->actingAs($returning)->post(route('invitations.accept', $invitation->token));

    expect($company->memberships()->where('user_id', $returning->id)->count())->toBe(1);
    expect($membership->fresh()->status)->toBe(MembershipStatus::Active);
});
