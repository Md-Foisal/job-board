<?php

use App\Enums\IdentityType;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Company;
use App\Models\Membership;
use App\Models\User;

test('guest is redirected to login', function () {
    $this->get(route('companies.create'))->assertRedirect(route('login'));
});

test('any signed-in person can open company setup', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('companies.create'))
        ->assertOk()
        ->assertSee('Set up your company');
});

test('creating a company makes its creator the owner', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('companies.store'), [
        'name' => 'Northwind Logistics',
        'identity_type' => 'company',
    ]);

    $company = Company::where('name', 'Northwind Logistics')->sole();

    expect($company->slug)->toBe('northwind-logistics');
    expect($company->identity_type)->toBe(IdentityType::Company);

    $membership = $company->memberships()->sole();
    expect($membership->user_id)->toBe($user->id);
    expect($membership->role)->toBe(MembershipRole::Owner);
    expect($membership->status)->toBe(MembershipStatus::Active);

    $response->assertRedirect(route('employer.dashboard', $company));
});

test('the creator can reach the workspace straight away', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('companies.store'), [
        'name' => 'Beacon Analytics',
        'identity_type' => 'agency',
    ]);

    $this->actingAs($user)
        ->get(route('employer.dashboard', Company::where('name', 'Beacon Analytics')->sole()))
        ->assertOk();
});

test('a second company with the same name gets its own slug', function () {
    Company::factory()->create(['name' => 'Bright Consulting', 'slug' => 'bright-consulting']);

    $this->actingAs(User::factory()->create())->post(route('companies.store'), [
        'name' => 'Bright Consulting',
        'identity_type' => 'company',
    ]);

    expect(Company::where('slug', 'bright-consulting-2')->exists())->toBeTrue();
});

test('a company needs a name and a type', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('companies.store'), ['name' => '', 'identity_type' => 'wizard'])
        ->assertSessionHasErrors(['name', 'identity_type']);

    expect(Company::count())->toBe(0);
});

test('registering to hire lands on company setup', function () {
    $this->post(route('register.store'), [
        'name' => 'Nadia Rahman',
        'email' => 'nadia@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'employer',
    ])->assertRedirect(route('companies.create'));
});

test('registering to look for work does not', function () {
    $this->post(route('register.store'), [
        'name' => 'Sakib Hasan',
        'email' => 'sakib@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'candidate',
    ])->assertRedirect(route('dashboard'));
});

test('an employer with a company skips the placeholder dashboard', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create();
    Membership::factory()->for($user)->for($company)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('employer.dashboard', $company));
});
