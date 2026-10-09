<?php

use App\Enums\IdentityType;
use App\Models\Company;
use App\Models\JobPosting;

test('the company page is named after the company in the browser tab', function () {
    $company = Company::factory()->create(['name' => 'Fernhill Software']);

    $this->get(route('companies.show', $company))
        ->assertOk()
        ->assertSee('Fernhill Software - '.config('app.name'));
});

test('the page says in plain words who is hiring', function (IdentityType $type, string $words) {
    $company = Company::factory()->create(['identity_type' => $type]);

    $this->get(route('companies.show', $company))->assertSee($words);
})->with([
    [IdentityType::Company, 'Direct employer'],
    [IdentityType::Agency, 'Recruitment agency'],
    [IdentityType::Individual, 'Individual employer'],
]);

test('its job cards do not repeat the company the page is about', function () {
    $company = Company::factory()->create();
    JobPosting::factory()->for($company)->create(['title' => 'Platform Engineer']);

    $this->get(route('companies.show', $company))
        ->assertSee('Platform Engineer')
        ->assertDontSeeHtml('href="'.route('companies.show', $company).'"');
});

test('the section links say how many jobs and reviews there are, and skip an empty about', function () {
    $company = Company::factory()->create(['description' => null]);
    JobPosting::factory()->count(2)->for($company)->create();

    $this->get(route('companies.show', $company))
        ->assertSeeHtml('href="#jobs"')
        ->assertSeeHtml('href="#reviews"')
        ->assertDontSeeHtml('href="#about"')
        ->assertSeeInOrder(['href="#jobs"', '2', 'href="#reviews"', '0'], false);
});

test('guests and candidates can save a job from the company page, employers cannot', function () {
    $company = Company::factory()->create();
    JobPosting::factory()->for($company)->create();

    $this->get(route('companies.show', $company))->assertSeeHtml('aria-label="Save job"');
    $this->actingAs(candidateUser())->get(route('companies.show', $company))->assertSeeHtml('aria-label="Save job"');
    $this->actingAs(employerUser())->get(route('companies.show', $company))->assertDontSeeHtml('aria-label="Save job"');
});

test('a job the candidate saved shows as saved on the company page', function () {
    $company = Company::factory()->create();
    $job = JobPosting::factory()->for($company)->create();
    $rafi = candidateUser();
    $rafi->savedJobs()->attach($job->id);

    $this->actingAs($rafi)
        ->get(route('companies.show', $company))
        ->assertSeeHtml('aria-pressed="true"');
});
