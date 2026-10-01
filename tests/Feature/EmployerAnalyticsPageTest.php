<?php

use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;
use App\Enums\MembershipRole;
use App\Enums\SkillImportance;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\JobPostingDailyStat;
use App\Models\Skill;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->job = JobPosting::factory()->for($this->company)->create([
        'title' => 'Backend Developer',
        'published_at' => now()->subDays(12),
        'expires_at' => now()->addDays(18),
        'salary_min' => null,
        'salary_max' => null,
    ]);
});

function analyticsPage($test, ?string $job = null, ?int $range = null)
{
    $query = array_filter(['job' => $job, 'range' => $range]);

    return $test->actingAs(employerUser($test->company))
        ->get(route('employer.analytics', ['company' => $test->company, ...$query]));
}

test('every member of the company can open it, not only managers', function (MembershipRole $role) {
    $this->actingAs(employerUser($this->company, $role))
        ->get(route('employer.analytics', $this->company))
        ->assertOk()
        ->assertSee('All job postings together');
})->with([MembershipRole::Owner, MembershipRole::Manager, MembershipRole::Member]);

test('people outside the company cannot', function () {
    $this->get(route('employer.analytics', $this->company))->assertRedirect(route('login'));

    $this->actingAs(employerUser())
        ->get(route('employer.analytics', $this->company))
        ->assertForbidden();
});

test('another company\'s posting, or one that does not exist, is not found', function () {
    $other = JobPosting::factory()->create();

    analyticsPage($this, $other->slug)->assertNotFound();
    analyticsPage($this, 'no-such-job')->assertNotFound();
});

test('the numbers on the page are the report\'s', function () {
    JobPostingDailyStat::create(['job_posting_id' => $this->job->id, 'date' => today()->toDateString(), 'views' => 40]);
    Application::factory()->count(2)->for($this->job)->create();
    $shortlisted = Application::factory()->for($this->job)->create(['stage' => ApplicationStage::Shortlisted]);
    ApplicationEvent::forceCreate(['application_id' => $shortlisted->id, 'to_stage' => 'shortlisted', 'created_at' => now()]);

    analyticsPage($this, $this->job->slug)
        ->assertOk()
        ->assertSeeInOrder(['Views', '40', 'Applications', '3', 'Apply rate', '7.5%'])
        ->assertSeeInOrder(['How far applicants got', 'Applied', '3', 'Shortlisted', '1', 'Interview', '0'])
        ->assertSeeInOrder(['Waiting on you now', '2']);
});

test('small numbers say there is not enough data rather than showing noise', function () {
    JobPostingDailyStat::create(['job_posting_id' => $this->job->id, 'date' => today()->toDateString(), 'views' => 5]);

    analyticsPage($this, $this->job->slug)
        ->assertSee('Not enough data yet: it needs 20 views.')
        ->assertSee('Not enough data yet: it needs 3 answered applications.')
        ->assertSee('No hires in this period.')
        ->assertSee('No applications in this period.');
});

test('applications left out of the match spread, and hires of earlier applicants, are explained', function () {
    $laravel = Skill::create(['name' => 'Laravel', 'slug' => 'laravel']);
    $this->job->skills()->attach([$laravel->id => ['importance' => SkillImportance::Required]]);

    Application::factory()->for($this->job)->create()
        ->candidateProfile->skills()->attach([$laravel->id => ['proficiency' => 'advanced']]);
    Application::factory()->count(2)->for($this->job)->create();

    $earlier = Application::factory()->for($this->job)->create([
        'created_at' => now()->subDays(45),
        'outcome_status' => ApplicationOutcomeStatus::Hired,
    ]);
    ApplicationEvent::forceCreate([
        'application_id' => $earlier->id,
        'from_outcome_status' => ApplicationOutcomeStatus::Active->value,
        'to_outcome_status' => ApplicationOutcomeStatus::Hired->value,
        'created_at' => now()->subDays(3),
    ]);

    analyticsPage($this, $this->job->slug)
        ->assertSee('2 applications have no score, because the posting or the applicant lists no skills.')
        ->assertSeeInOrder(['Hired', '0'])
        ->assertSee('One hire in this period; a typical time needs 3.')
        ->assertSee('Counted on the day of the hire, so it can include someone who applied before this period.');
});

test('a range that is not offered falls back to thirty days', function () {
    analyticsPage($this, range: 14)->assertOk();

    Livewire::actingAs(employerUser($this->company))
        ->withQueryParams(['range' => 14])
        ->test('pages::employer.analytics', ['company' => $this->company])
        ->assertSet('range', 30)
        ->set('range', 7)
        ->assertSet('range', 7)
        ->set('range', 365)
        ->assertSet('range', 30);
});

test('choosing a posting narrows the page to it and shows what could help it', function () {
    Livewire::actingAs(employerUser($this->company, MembershipRole::Manager))
        ->test('pages::employer.analytics', ['company' => $this->company])
        ->assertDontSee('What could help this posting')
        ->set('job', $this->job->slug)
        ->assertSee('Backend Developer')
        ->assertSee('What could help this posting')
        ->assertSee('No salary is shown.')
        ->assertSee('Live for 12 days.')
        ->assertSee('Edit the posting');
});

test('a plain member sees the suggestions but no edit button', function () {
    $this->actingAs(employerUser($this->company))
        ->get(route('employer.analytics', ['company' => $this->company, 'job' => $this->job->slug]))
        ->assertSee('No salary is shown.')
        ->assertDontSee('Edit the posting');
});

test('a draft says it is not published yet', function () {
    $draft = JobPosting::factory()->for($this->company)->draft()->create(['published_at' => null]);

    analyticsPage($this, $draft->slug)->assertSee('Not published yet.');
});

test('a company without postings is told what will appear here', function () {
    $empty = Company::factory()->create();

    $this->actingAs(employerUser($empty, MembershipRole::Owner))
        ->get(route('employer.analytics', $empty))
        ->assertOk()
        ->assertSee('Nothing to measure yet')
        ->assertSee('Post a job');
});

test('the page says when views started being counted, so an empty start is not read as nobody coming', function () {
    JobPostingDailyStat::create(['job_posting_id' => $this->job->id, 'date' => today()->subDays(3)->toDateString(), 'views' => 2]);

    analyticsPage($this)->assertSee('Views have been counted since '.today()->subDays(3)->toFormattedDateString());
});

test('each chart has its table, with every day of the range', function () {
    JobPostingDailyStat::create(['job_posting_id' => $this->job->id, 'date' => today()->toDateString(), 'views' => 9]);

    analyticsPage($this, range: 7)
        ->assertSee('Views per day')
        ->assertSee('Applications per day')
        ->assertSee('Show as table')
        ->assertSee('Views per day: 9 in total, highest 9 on '.today()->toFormattedDateString().'.')
        ->assertSee(today()->subDays(6)->toFormattedDateString())
        ->assertDontSee(today()->subDays(7)->toFormattedDateString());
});

test('the page is linked from the dashboard, the sidebar and each posting', function () {
    $member = employerUser($this->company);

    $this->actingAs($member)
        ->get(route('employer.dashboard', $this->company))
        ->assertSee(route('employer.analytics', $this->company), false);

    Livewire::actingAs($member)
        ->test('pages::employer.job-listings', ['company' => $this->company])
        ->assertSee(route('employer.analytics', ['company' => $this->company, 'job' => $this->job->slug]), false)
        ->assertSee('Stats for Backend Developer');
});
