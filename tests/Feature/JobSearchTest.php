<?php

use App\Enums\EmploymentType;
use App\Enums\SkillImportance;
use App\Enums\WorkplaceType;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Skill;
use Livewire\Livewire;

test('guest sees active job postings on the search page', function () {
    JobPosting::factory()->create(['title' => 'Backend Engineer']);
    JobPosting::factory()->draft()->create(['title' => 'Hidden Draft Role']);
    JobPosting::factory()->pendingModeration()->create(['title' => 'Awaiting Review Role']);

    $response = $this->get(route('jobs.index'));

    $response->assertOk();
    $response->assertSee('Backend Engineer');
    $response->assertDontSee('Hidden Draft Role');
    $response->assertDontSee('Awaiting Review Role');
});

test('keyword filter narrows results by job title', function () {
    JobPosting::factory()->create(['title' => 'Senior Laravel Developer']);
    JobPosting::factory()->create(['title' => 'Marketing Manager']);

    Livewire::test('pages::job-search')
        ->set('q', 'Laravel')
        ->assertSee('Senior Laravel Developer')
        ->assertDontSee('Marketing Manager');
});

test('the search box matches a company name or a skill, not only the title', function () {
    $laravel = Skill::create(['name' => 'Laravel']);

    JobPosting::factory()
        ->for(Company::factory()->create(['name' => 'Fernhill Software']))
        ->create(['title' => 'Customer Support Specialist']);
    JobPosting::factory()->create(['title' => 'Backend Developer'])->skills()->attach($laravel->id, ['importance' => SkillImportance::Required->value]);
    JobPosting::factory()->create(['title' => 'Marketing Manager']);

    Livewire::test('pages::job-search')
        ->set('q', 'Fernhill')
        ->assertSee('Customer Support Specialist')
        ->assertDontSee('Marketing Manager')
        ->set('q', 'laravel')
        ->assertSee('Backend Developer')
        ->assertDontSee('Customer Support Specialist');
});

test('date posted keeps only recent postings, and stays out of the alert link', function () {
    JobPosting::factory()->create(['title' => 'Fresh Role', 'published_at' => now()->subDays(2)]);
    JobPosting::factory()->create(['title' => 'Older Role', 'published_at' => now()->subDays(10)]);

    Livewire::test('pages::job-search')
        ->set('posted', 3)
        ->assertSee('Fresh Role')
        ->assertDontSee('Older Role')
        ->assertSee('Past 3 days')
        ->assertDontSee('posted=3');
});

test('an address with a date posted the page does not offer opens with every date', function () {
    JobPosting::factory()->create(['title' => 'Older Role', 'published_at' => now()->subDays(40)]);

    Livewire::withQueryParams(['posted' => 30])
        ->test('pages::job-search')
        ->assertSet('posted', null)
        ->assertSee('Older Role');
});

test('each filter in force is a chip that removes it, and clearing all keeps the search words', function () {
    JobPosting::factory()->create([
        'title' => 'Laravel Contract Role',
        'employment_type' => EmploymentType::Contract,
        'workplace_type' => WorkplaceType::Hybrid,
        'location_city' => 'London',
    ]);
    JobPosting::factory()->create(['title' => 'Laravel Full-time Role', 'employment_type' => EmploymentType::FullTime, 'location_city' => 'London']);

    Livewire::test('pages::job-search')
        ->set('q', 'Laravel')
        ->set('location', 'London')
        ->set('employmentType', EmploymentType::Contract->value)
        ->set('workplaceType', WorkplaceType::Hybrid->value)
        ->assertSee('Remove filter: '.EmploymentType::Contract->label())
        ->assertSee('Remove filter: '.WorkplaceType::Hybrid->label())
        ->assertDontSee('Laravel Full-time Role')
        ->call('clearFilter', 'employmentType')
        ->assertSet('employmentType', null)
        ->assertSet('workplaceType', WorkplaceType::Hybrid->value)
        ->call('resetFilters')
        ->assertSet('workplaceType', null)
        ->assertSet('q', 'Laravel')
        ->assertSet('location', 'London')
        ->assertSee('Laravel Full-time Role');
});

test('a filter name the page does not know clears nothing', function () {
    Livewire::test('pages::job-search')
        ->set('q', 'Laravel')
        ->call('clearFilter', 'q')
        ->assertSet('q', 'Laravel');
});

test('remote is one tap, and typing remote as the place means the same', function () {
    JobPosting::factory()->create(['title' => 'Remote Role', 'workplace_type' => WorkplaceType::Remote, 'location_city' => null]);
    JobPosting::factory()->create(['title' => 'Office Role', 'workplace_type' => WorkplaceType::Onsite, 'location_city' => 'Leeds']);

    Livewire::test('pages::job-search')
        ->call('toggleRemote')
        ->assertSet('workplaceType', WorkplaceType::Remote->value)
        ->assertSeeHtml('aria-pressed="true"')
        ->assertDontSee('Office Role')
        ->call('toggleRemote')
        ->assertSet('workplaceType', null)
        ->set('location', 'Remote')
        ->assertSet('location', '')
        ->assertSet('workplaceType', WorkplaceType::Remote->value)
        ->assertSee('Remote Role')
        ->assertDontSee('Office Role');
});

test('guests and candidates can save from the results, employers cannot', function () {
    JobPosting::factory()->create();

    Livewire::test('pages::job-search')->assertSeeHtml('aria-label="Save job"');
    Livewire::actingAs(candidateUser())->test('pages::job-search')->assertSeeHtml('aria-label="Save job"');
    Livewire::actingAs(employerUser())->test('pages::job-search')->assertDontSeeHtml('aria-label="Save job"');
});

test('a card shows a saved job as saved', function () {
    $job = JobPosting::factory()->create();
    $rafi = candidateUser();
    $rafi->savedJobs()->attach($job->id);

    Livewire::actingAs($rafi)
        ->test('pages::job-search')
        ->assertSeeHtml('aria-pressed="true"');
});

test('a card says nothing about a 0% match', function () {
    $laravel = Skill::create(['name' => 'Laravel']);
    $go = Skill::create(['name' => 'Go']);

    // Laravel required (counts twice) and Go nice to have: Rafi covers 2 of 3.
    JobPosting::factory()->create(['title' => 'Laravel Role'])->skills()->attach([
        $laravel->id => ['importance' => SkillImportance::Required->value],
        $go->id => ['importance' => SkillImportance::NiceToHave->value],
    ]);
    JobPosting::factory()->create(['title' => 'Go Role'])->skills()->attach($go->id, ['importance' => SkillImportance::Required->value]);

    $rafi = candidateUser();
    $rafi->candidateProfile->skills()->attach($laravel->id, ['proficiency' => 'advanced']);

    Livewire::actingAs($rafi)
        ->test('pages::job-search')
        ->assertSee('Go Role')
        ->assertSee('67% match')
        ->assertDontSee('0% match');
});
