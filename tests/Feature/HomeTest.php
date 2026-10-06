<?php

use App\Enums\EmploymentType;
use App\Enums\MembershipRole;
use App\Enums\SalaryPeriod;
use App\Enums\WorkplaceType;
use App\Livewire\JobSearchAutocomplete;
use App\Models\Category;
use App\Models\Company;
use App\Models\JobPosting;
use App\Support\CategoryIcon;
use Livewire\Livewire;

test('shows active job postings', function () {
    $job = JobPosting::factory()->create(['title' => 'Senior Laravel Developer']);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSee('Senior Laravel Developer');
});

test('hides draft job postings', function () {
    JobPosting::factory()->draft()->create(['title' => 'Hidden Draft Role']);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertDontSee('Hidden Draft Role');
});

test('hides job postings pending moderation', function () {
    JobPosting::factory()->pendingModeration()->create(['title' => 'Awaiting Approval Role']);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertDontSee('Awaiting Approval Role');
});

test('hides expired job postings', function () {
    JobPosting::factory()->create([
        'title' => 'Expired Role',
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertDontSee('Expired Role');
});

test('shows categories with their active job posting count', function () {
    $category = Category::create(['name' => 'Engineering', 'slug' => 'engineering']);
    $job = JobPosting::factory()->create();
    $job->categories()->attach($category);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSee('Engineering');
});

test('lists every category with an open job, and none without one', function () {
    $job = JobPosting::factory()->create();
    $names = ['Accounts', 'Biology', 'Catering', 'Dentistry', 'Energy', 'Fashion', 'Gardening', 'Hospitality', 'Insurance', 'Journalism'];

    foreach ($names as $name) {
        $job->categories()->attach(Category::create(['name' => $name, 'slug' => strtolower($name)]));
    }

    Category::create(['name' => 'Unused Field', 'slug' => 'unused-field']);
    JobPosting::factory()->draft()->create()->categories()->attach(Category::create(['name' => 'Draft-only Field', 'slug' => 'draft-only-field']));

    $response = $this->get(route('home'))->assertOk();

    foreach ($names as $name) {
        $response->assertSee($name);
    }

    $response->assertDontSee('Unused Field')->assertDontSee('Draft-only Field');
});

test('the hero search sends what and where to the search page', function () {
    Livewire::test(JobSearchAutocomplete::class, ['variant' => 'hero'])
        ->set('q', ' Laravel ')
        ->set('where', ' London ')
        ->call('goToSearch')
        ->assertRedirect(route('jobs.index', ['q' => 'Laravel', 'location' => 'London']));
});

test('remote in the where box searches remote jobs rather than a city called remote', function () {
    Livewire::test(JobSearchAutocomplete::class, ['variant' => 'hero'])
        ->set('where', 'Remote')
        ->call('goToSearch')
        ->assertRedirect(route('jobs.index', ['workplaceType' => WorkplaceType::Remote->value]));
});

test('an empty hero search opens the full listing', function () {
    Livewire::test(JobSearchAutocomplete::class, ['variant' => 'hero'])
        ->call('goToSearch')
        ->assertRedirect(route('jobs.index'));
});

test('the hero counts open roles and the companies hiring', function () {
    JobPosting::factory()->count(2)->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('2 open roles')
        ->assertSee('from 2 companies hiring now')
        ->assertSee('Browse all 2 open jobs');
});

test('an empty board says so instead of showing an empty grid', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('No open jobs right now')
        ->assertDontSee('open roles');
});

test('the hiring band takes someone who can post straight to the job form', function () {
    $company = Company::factory()->create();

    $this->actingAs(employerUser($company, MembershipRole::Owner))
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Hiring? Meet people who already match.')
        ->assertSee(route('employer.jobs.create', $company), escape: false);
});

test('a job card shows where, the job type and the pay with its period', function () {
    JobPosting::factory()->create([
        'location_city' => 'London',
        'workplace_type' => WorkplaceType::Hybrid,
        'employment_type' => EmploymentType::FullTime,
        'salary_negotiable' => false,
        'salary_currency' => 'GBP',
        'salary_min' => 72000,
        'salary_max' => 90000,
        'salary_period' => SalaryPeriod::Yearly,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['London · Hybrid', EmploymentType::FullTime->label(), '£72,000–£90,000', 'a year']);
});

test('a job card with negotiable pay says so plainly', function () {
    JobPosting::factory()->create([
        'salary_negotiable' => true,
        'salary_currency' => null,
        'salary_min' => null,
        'salary_max' => null,
        'salary_period' => null,
    ]);

    $this->get(route('home'))->assertOk()->assertSee('Pay negotiable');
});

test('known categories have their own icon and a new one falls back to a briefcase', function () {
    expect(CategoryIcon::for(new Category(['slug' => 'engineering'])))->toBe('code-bracket')
        ->and(CategoryIcon::for(new Category(['slug' => 'aquaculture'])))->toBe('briefcase');
});

test('the page carries the gradient its icons are painted with', function () {
    $this->get(route('home'))->assertOk()->assertSee('id="sunset-icon"', escape: false);
});
