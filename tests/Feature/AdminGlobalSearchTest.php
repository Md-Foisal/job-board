<?php

use App\Enums\StaffRole;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\JobPostings\JobPostingResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\User;

it('opens a search result in the tab that holds it, so its dialog can open', function () {
    $this->actingAs(staffWithTwoFactor());

    $approved = JobPosting::factory()->create();
    $waiting = JobPosting::factory()->pendingModeration()->create();
    $verified = Company::factory()->create(['verified_at' => now()]);

    expect(JobPostingResource::getGlobalSearchResultUrl($approved))->toContain('tab=approved')->toContain('tableAction=view')
        ->and(JobPostingResource::getGlobalSearchResultUrl($waiting))->toContain('tab=pending')
        ->and(CompanyResource::getGlobalSearchResultUrl($verified))->toContain('tab=verified');
});

it('finds a person by email and shows the address beside the name', function () {
    $this->actingAs(staffWithTwoFactor(StaffRole::SuperAdmin));
    $person = User::factory()->create(['name' => 'Ada Moore', 'email' => 'ada.moore@example.org']);

    $results = UserResource::getGlobalSearchResults('ada.moore@');

    expect($results->map->title->all())->toContain('Ada Moore')
        ->and(UserResource::getGlobalSearchResultDetails($person))->toBe(['Email' => 'ada.moore@example.org']);
});

it('keeps each group of search results short', function () {
    expect(JobPostingResource::getGlobalSearchResultsLimit())->toBe(5);
});
