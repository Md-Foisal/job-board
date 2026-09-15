<?php

use App\Enums\IdentityType;
use App\Enums\MembershipRole;
use App\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('an owner can open the company profile', function () {
    $company = Company::factory()->create(['name' => 'Northwind Logistics']);

    $this->actingAs(employerUser($company, MembershipRole::Owner))
        ->get(route('employer.company.edit', $company))
        ->assertOk()
        ->assertSee('Northwind Logistics');
});

test('a plain member cannot open or change the company profile', function () {
    $company = Company::factory()->create();
    $member = employerUser($company, MembershipRole::Member);

    $this->actingAs($member)
        ->get(route('employer.company.edit', $company))
        ->assertForbidden();

    $this->actingAs($member)
        ->patch(route('employer.company.update', $company), [
            'name' => 'Renamed By A Member',
            'identity_type' => 'company',
        ])
        ->assertForbidden();

    expect($company->fresh()->name)->not->toBe('Renamed By A Member');
});

test('someone from another company cannot reach it at all', function () {
    $company = Company::factory()->create();

    $this->actingAs(employerUser())
        ->get(route('employer.company.edit', $company))
        ->assertForbidden();
});

test('a manager can update the profile', function () {
    $company = Company::factory()->create(['industry' => null, 'size' => null]);

    $this->actingAs(employerUser($company, MembershipRole::Manager))
        ->patch(route('employer.company.update', $company), [
            'name' => 'Beacon Analytics',
            'identity_type' => 'agency',
            'description' => 'We place data engineers.',
            'website_url' => 'https://beacon.example',
            'industry' => 'Software',
            'size' => '11-50',
        ])
        ->assertRedirect();

    $company->refresh();

    expect($company->name)->toBe('Beacon Analytics');
    expect($company->identity_type)->toBe(IdentityType::Agency);
    expect($company->industry)->toBe('Software');
    expect($company->size)->toBe('11-50');
});

test('the public slug does not move when the name changes', function () {
    $company = Company::factory()->create(['name' => 'Old Name', 'slug' => 'old-name']);

    $this->actingAs(employerUser($company, MembershipRole::Owner))
        ->patch(route('employer.company.update', $company), [
            'name' => 'Brand New Name',
            'identity_type' => 'company',
        ]);

    expect($company->fresh()->slug)->toBe('old-name');
});

test('an invalid website is rejected', function () {
    $company = Company::factory()->create(['name' => 'Unchanged Ltd']);

    $this->actingAs(employerUser($company, MembershipRole::Owner))
        ->patch(route('employer.company.update', $company), [
            'name' => 'Something Else',
            'identity_type' => 'company',
            'website_url' => 'not-a-url',
        ])
        ->assertSessionHasErrors('website_url');

    expect($company->fresh()->name)->toBe('Unchanged Ltd');
});

test('uploading a new logo replaces the old file rather than leaving it behind', function () {
    Storage::fake('public');

    $company = Company::factory()->create([
        'logo_path' => UploadedFile::fake()->image('old.png')->store('company-logos', 'public'),
    ]);
    $originalPath = $company->logo_path;

    Storage::disk('public')->assertExists($originalPath);

    $this->actingAs(employerUser($company, MembershipRole::Owner))
        ->patch(route('employer.company.update', $company), [
            'name' => $company->name,
            'identity_type' => $company->identity_type->value,
            'logo' => UploadedFile::fake()->image('new.png'),
        ]);

    $company->refresh();

    expect($company->logo_path)->not->toBe($originalPath);
    Storage::disk('public')->assertExists($company->logo_path);
    Storage::disk('public')->assertMissing($originalPath);
});

test('saving without touching the files keeps the existing ones', function () {
    Storage::fake('public');

    $company = Company::factory()->create([
        'logo_path' => UploadedFile::fake()->image('logo.png')->store('company-logos', 'public'),
    ]);
    $originalPath = $company->logo_path;

    $this->actingAs(employerUser($company, MembershipRole::Owner))
        ->patch(route('employer.company.update', $company), [
            'name' => 'Still Here Ltd',
            'identity_type' => $company->identity_type->value,
        ]);

    expect($company->fresh()->logo_path)->toBe($originalPath);
    Storage::disk('public')->assertExists($originalPath);
});

test('the verification state is shown but not editable', function () {
    $company = Company::factory()->create(['verified_at' => null]);
    $owner = employerUser($company, MembershipRole::Owner);

    $this->actingAs($owner)
        ->get(route('employer.company.edit', $company))
        ->assertSee('Pending verification');

    // Set directly rather than mass-assigned: verification is the
    // platform's to grant, so the column is deliberately not fillable.
    $company->verified_at = now();
    $company->save();

    $this->actingAs($owner)
        ->get(route('employer.company.edit', $company))
        ->assertSee('Verified');
});
