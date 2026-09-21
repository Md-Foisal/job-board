<?php

use App\Actions\MergeCategory;
use App\Actions\MergeSkill;
use App\Enums\AlertFrequency;
use App\Models\Category;
use App\Models\JobAlert;
use App\Models\JobPosting;
use App\Models\Skill;
use App\Support\JobSearchCriteria;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('a candidate saves a job alert with its filters', function () {
    $candidate = candidateUser();
    $skill = Skill::create(['name' => 'Laravel']);

    Livewire::actingAs($candidate)
        ->test('pages::candidate.job-alerts')
        ->call('create')
        ->set('name', 'Laravel jobs')
        ->set('frequency', AlertFrequency::Weekly->value)
        ->set('q', 'Developer')
        ->set('skill', $skill->id)
        ->set('location', 'Dhaka')
        ->call('save')
        ->assertHasNoErrors();

    $jobAlert = $candidate->jobAlerts()->sole();

    expect($jobAlert->name)->toBe('Laravel jobs')
        ->and($jobAlert->frequency)->toBe(AlertFrequency::Weekly)
        ->and($jobAlert->is_active)->toBeTrue()
        ->and($jobAlert->criteria)->toBe(['q' => 'Developer', 'skill' => $skill->id, 'location' => 'Dhaka']);
});

test('the search page hands its filters to a new alert', function () {
    $candidate = candidateUser();

    $this->actingAs($candidate)
        ->get(route('candidate.job-alerts.index', ['create' => 1, 'q' => 'Designer', 'workplaceType' => 'remote', 'bogus' => 'x']))
        ->assertOk()
        ->assertSee('Designer');

    Livewire::actingAs($candidate)
        ->withQueryParams(['create' => 1, 'q' => 'Designer', 'workplaceType' => 'remote', 'bogus' => 'x'])
        ->test('pages::candidate.job-alerts')
        ->assertSet('showModal', true)
        ->assertSet('q', 'Designer')
        ->assertSet('workplaceType', 'remote')
        ->call('save')
        ->assertHasNoErrors();

    expect($candidate->jobAlerts()->sole()->criteria)->toBe(['q' => 'Designer', 'workplaceType' => 'remote']);
});

test('the search page offers an alert for the search on screen, but not to employers', function () {
    Livewire::actingAs(candidateUser())
        ->withQueryParams(['q' => 'Designer'])
        ->test('pages::job-search')
        ->assertSee('Create job alert')
        ->assertSee(route('candidate.job-alerts.index', ['create' => 1, 'q' => 'Designer']));

    Livewire::actingAs(employerUser())
        ->test('pages::job-search')
        ->assertDontSee('Create job alert');
});

test('a candidate cannot keep more alerts than the limit', function () {
    $candidate = candidateUser();
    JobAlert::factory()->count(JobAlert::MAX_PER_CANDIDATE)->for($candidate)->create();

    Livewire::actingAs($candidate)
        ->test('pages::candidate.job-alerts')
        ->call('create')
        ->assertSet('showModal', false);

    expect($candidate->jobAlerts()->count())->toBe(JobAlert::MAX_PER_CANDIDATE);
});

test('an alert is paused, resumed, edited and deleted only by its owner', function () {
    $candidate = candidateUser();
    $jobAlert = JobAlert::factory()->for($candidate)->create();
    $other = JobAlert::factory()->create();

    $page = Livewire::actingAs($candidate)->test('pages::candidate.job-alerts');

    $page->call('toggle', $jobAlert->id);
    expect($jobAlert->fresh()->is_active)->toBeFalse();

    $page->call('toggle', $jobAlert->id);
    expect($jobAlert->fresh()->is_active)->toBeTrue();

    $page->call('edit', $jobAlert->id)->set('name', 'Renamed')->call('save');
    expect($jobAlert->fresh()->name)->toBe('Renamed');

    expect(fn () => $page->call('delete', $other->id))->toThrow(ModelNotFoundException::class);

    $page->call('delete', $jobAlert->id);
    expect(JobAlert::find($jobAlert->id))->toBeNull()
        ->and(JobAlert::find($other->id))->not->toBeNull();
});

test('an alert refuses criteria that do not exist', function () {
    Livewire::actingAs(candidateUser())
        ->test('pages::candidate.job-alerts')
        ->call('create')
        ->set('name', 'Broken')
        ->set('skill', 999999)
        ->set('salaryMin', 50000)
        ->set('salaryMax', 1000)
        ->call('save')
        ->assertHasErrors(['skill', 'salaryMax']);
});

test('job alerts are for candidates only', function () {
    $this->actingAs(employerUser())
        ->get(route('candidate.job-alerts.index'))
        ->assertForbidden();
});

test('an alert matches exactly what the search page shows for the same filters', function () {
    $skill = Skill::create(['name' => 'Vue']);
    $match = JobPosting::factory()->create(['title' => 'Vue Developer', 'location_city' => 'Dhaka']);
    $match->skills()->attach($skill->id, ['importance' => 'required']);
    JobPosting::factory()->create(['title' => 'Vue Developer', 'location_city' => 'Chittagong']);
    JobPosting::factory()->create(['title' => 'Accountant', 'location_city' => 'Dhaka']);

    $criteria = JobSearchCriteria::from(['q' => 'Vue', 'skill' => $skill->id, 'location' => 'Dhaka']);

    expect(JobPosting::query()->active()->matching($criteria)->pluck('id')->all())->toBe([$match->id]);
});

test('unknown or empty filters are dropped rather than trusted', function () {
    expect(JobSearchCriteria::from([
        'q' => '  ',
        'skill' => 'abc',
        'salaryMin' => '0',
        'experience' => '-3',
        'workplaceType' => 'on-the-moon',
        'employmentType' => 'full-time',
        'somethingElse' => 'x',
    ]))->toBe(['employmentType' => 'full-time']);
});

test('merging a skill or a category carries the alerts that asked for it', function () {
    $reactJs = Skill::create(['name' => 'ReactJS']);
    $react = Skill::create(['name' => 'React']);
    $frontEnd = Category::create(['name' => 'Front End', 'slug' => 'front-end']);
    $web = Category::create(['name' => 'Web', 'slug' => 'web']);

    $jobAlert = JobAlert::factory()->create(['criteria' => ['q' => 'Developer', 'skill' => $reactJs->id, 'category' => $frontEnd->id]]);

    app(MergeSkill::class)($reactJs, $react);
    app(MergeCategory::class)($frontEnd, $web);

    expect($jobAlert->fresh()->criteria)->toBe(['q' => 'Developer', 'skill' => $react->id, 'category' => $web->id]);
});
