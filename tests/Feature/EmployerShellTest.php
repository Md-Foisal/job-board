<?php

use App\Models\Company;
use App\Models\Membership;
use App\Models\User;

test('a member lands on the company workspace', function () {
    $company = Company::factory()->create(['name' => 'Northwind Logistics']);
    $member = employerUser($company);

    $this->actingAs($member)
        ->get(route('employer.dashboard', $company))
        ->assertOk()
        ->assertSee('Northwind Logistics');
});

test('the workspace carries no public job-browsing controls', function () {
    $company = Company::factory()->create();

    $response = $this->actingAs(employerUser($company))
        ->get(route('employer.dashboard', $company));

    // Shell A's navbar offers these; Shell C deliberately does not, so that
    // someone mid-review is not invited back out into browsing.
    $response->assertDontSee(route('jobs.index'));
    $response->assertDontSee('For Employers');
});

test('the switcher stays hidden when there is only one context', function () {
    $company = Company::factory()->create(['name' => 'Solo Studio']);
    $user = User::factory()->create();
    Membership::factory()->for($user)->for($company)->create();

    $response = $this->actingAs($user)->get(route('employer.dashboard', $company));

    $response->assertOk();
    $response->assertSee('Solo Studio');
    // Nothing to switch to, so no other destination is offered.
    $response->assertDontSee(route('candidate.dashboard'));
});

test('the switcher lists every context once there is more than one', function () {
    $first = Company::factory()->create(['name' => 'Acme Interiors']);
    $second = Company::factory()->create(['name' => 'Beacon Analytics']);

    $user = candidateUser();
    Membership::factory()->for($user)->for($first)->create();
    Membership::factory()->for($user)->for($second)->create();

    $response = $this->actingAs($user)->get(route('employer.dashboard', $first));

    $response->assertOk();
    $response->assertSee('Acme Interiors');
    $response->assertSee('Beacon Analytics');
    // The personal side is a peer in the same list, not a separate menu.
    $response->assertSee(route('candidate.dashboard'));
});

test('an ended membership drops out of the switcher', function () {
    $kept = Company::factory()->create(['name' => 'Still Here Ltd']);
    $left = Company::factory()->create(['name' => 'Former Employer Ltd']);

    $user = candidateUser();
    Membership::factory()->for($user)->for($kept)->create();
    $old = Membership::factory()->for($user)->for($left)->create();
    $old->update(['status' => \App\Enums\MembershipStatus::Inactive]);

    $response = $this->actingAs($user)->get(route('employer.dashboard', $kept));

    $response->assertOk();
    $response->assertDontSee('Former Employer Ltd');
});
