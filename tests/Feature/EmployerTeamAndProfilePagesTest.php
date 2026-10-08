<?php

use App\Enums\MembershipRole;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'Fernhill Software']);
    $this->owner = employerUser($this->company, MembershipRole::Owner);
});

test('every role on the team says what it opens', function () {
    employerUser($this->company, MembershipRole::Member);

    Livewire::actingAs($this->owner)
        ->test('pages::employer.team', ['company' => $this->company])
        ->assertSee('(you)')
        ->assertSee(MembershipRole::Owner->description())
        ->assertSee(MembershipRole::Member->description());
});

test('a team of one is invited to grow, and a pending invitation names its role and who sent it', function () {
    Livewire::actingAs($this->owner)
        ->test('pages::employer.team', ['company' => $this->company])
        ->assertSee('Just you so far');

    Invitation::factory()->for($this->company)->create([
        'invited_by_id' => $this->owner->id,
        'email' => 'priya.shah@example.org',
        'role' => MembershipRole::Owner,
    ]);

    Livewire::actingAs($this->owner)
        ->test('pages::employer.team', ['company' => $this->company])
        ->assertDontSee('Just you so far')
        ->assertSee('Invited as Owner by '.$this->owner->name)
        ->assertDontSee('a owner');
});

test('the invitation page sits in the site frame and says what the role opens', function () {
    $invitation = Invitation::factory()->for($this->company)->create([
        'invited_by_id' => $this->owner->id,
        'email' => 'priya.shah@example.org',
        'role' => MembershipRole::Manager,
    ]);

    $this->get(route('invitations.show', $invitation->token))
        ->assertOk()
        ->assertSee('Join Fernhill Software')
        ->assertSee('Role: Manager')
        ->assertSee(MembershipRole::Manager->description())
        ->assertSee(route('jobs.index'), false);
});

test('the company profile links to the page candidates see, and picks pictures without the browser box', function () {
    $this->actingAs($this->owner)
        ->get(route('employer.company.edit', $this->company))
        ->assertOk()
        ->assertSee(route('companies.show', $this->company), false)
        ->assertSee('name="logo"', false)
        ->assertSee('name="cover_photo"', false)
        ->assertSee('class="sr-only"', false);
});

test('the recruiter profile shows the card candidates will see, falling back to the account name', function () {
    $recruiter = User::factory()->create(['name' => 'Hannah Lewis']);
    Membership::factory()->for($this->company)->for($recruiter, 'user')->create();

    $this->actingAs($recruiter)
        ->get(route('employer.recruiter-profile.edit'))
        ->assertOk()
        ->assertSeeInOrder(['What candidates see on your jobs', 'Fernhill Software', 'Posted by', 'Hannah Lewis']);

    $recruiter->recruiterProfile()->create(['display_name' => 'Hannah from Fernhill', 'bio' => 'I hire for support.']);

    // fresh(): the first request left the empty profile cached on this
    // same user object.
    $this->actingAs($recruiter->fresh())
        ->get(route('employer.recruiter-profile.edit'))
        ->assertSeeInOrder(['Posted by', 'Hannah from Fernhill', 'I hire for support.']);
});
