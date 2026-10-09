<?php

use App\Actions\ChangeApplicationOutcome;
use App\Actions\RejectJobPosting;
use App\Actions\RequestCompanyDocuments;
use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;
use App\Enums\AvailabilityStatus;
use App\Enums\MembershipRole;
use App\Enums\PostingState;
use App\Enums\SalaryPeriod;
use App\Models\Application;
use App\Models\Category;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\JobPosting;
use App\Models\Report;
use App\Models\Skill;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'Fernhill Software', 'timezone' => 'Europe/London']);
    $this->owner = employerUser($this->company, MembershipRole::Owner);
    $this->member = employerUser($this->company);
});

function workspaceDashboard($test, User $user)
{
    return $test->actingAs($user)->get(route('employer.dashboard', $test->company))->assertOk();
}

function hideByReports(JobPosting $jobPosting): void
{
    foreach (User::factory()->count(Report::HIDE_AFTER_REPORTERS)->create() as $reporter) {
        $jobPosting->reports()->create(['reporter_id' => $reporter->id, 'reason' => 'Spam']);
    }
}

test('needs you names each job with new applications and how long the oldest has waited', function () {
    $job = JobPosting::factory()->for($this->company)->create(['title' => 'Backend Developer']);
    Application::factory()->for($job)->create(['created_at' => now()->subDays(6)]);
    Application::factory()->for($job)->create();
    Application::factory()->for($job)->create(['outcome_status' => ApplicationOutcomeStatus::Withdrawn]);

    workspaceDashboard($this, $this->member)
        ->assertSee('2 new applications for Backend Developer')
        ->assertSee('The oldest has waited 6 days')
        ->assertSee(route('employer.jobs.applications', ['company' => $this->company, 'jobPosting' => $job, 'stage' => 'new']), false);
});

test('needs you names five jobs and counts the rest in one line', function () {
    JobPosting::factory()->for($this->company)->count(7)->create()
        ->each(fn (JobPosting $job) => Application::factory()->for($job)->create());

    workspaceDashboard($this, $this->member)->assertSee('2 more jobs have new applications');
});

test('an owner sees the work only owners and managers can do, a member does not', function () {
    $staff = staffWithTwoFactor();

    $sentBack = JobPosting::factory()->for($this->company)->pendingModeration()->create(['title' => 'Support Lead']);
    app(RejectJobPosting::class)($sentBack, $staff, 'Salary range is missing.');

    JobPosting::factory()->for($this->company)->create(['title' => 'Data Analyst', 'expires_at' => now()->addDays(2)->addHour()]);
    JobPosting::factory()->for($this->company)->draft()->create();
    Invitation::factory()->for($this->company)->create();
    app(RequestCompanyDocuments::class)($this->company, $staff, 'A trade licence, please.');

    $decided = Application::factory()->for(JobPosting::factory()->for($this->company)->create(['title' => 'Designer']))->create();
    app(ChangeApplicationOutcome::class)($decided, $this->owner, ApplicationOutcomeStatus::Rejected);

    workspaceDashboard($this, $this->owner)
        ->assertSee('You can still undo the decision on '.$decided->candidateProfile->user->name)
        ->assertSee('Support Lead needs changes before it goes live')
        ->assertSee('Salary range is missing.')
        ->assertSee('Data Analyst closes in 2 days')
        ->assertSee('Our team asked for documents')
        ->assertSee('1 draft is not published yet')
        ->assertSee('1 invitation is not accepted yet');

    workspaceDashboard($this, $this->member)
        ->assertDontSee('You can still undo')
        ->assertDontSee('needs changes before it goes live')
        ->assertDontSee('closes in 2 days')
        ->assertDontSee('Our team asked for documents')
        ->assertDontSee('not published yet')
        ->assertDontSee('not accepted yet');
});

test('a decision leaves needs you once its undo window has passed', function () {
    $decided = Application::factory()->for(JobPosting::factory()->for($this->company)->create())->create();
    app(ChangeApplicationOutcome::class)($decided, $this->owner, ApplicationOutcomeStatus::Hired);

    $this->travel(Application::UNDO_MINUTES + 1)->minutes();

    workspaceDashboard($this, $this->owner)->assertDontSee('You can still undo');
});

test('the pipeline counts only applications still in play at each stage', function () {
    $job = JobPosting::factory()->for($this->company)->create(['title' => 'Backend Developer']);
    Application::factory()->for($job)->create(['stage' => ApplicationStage::Interview]);
    Application::factory()->for($job)->create(['stage' => ApplicationStage::Interview, 'outcome_status' => ApplicationOutcomeStatus::Rejected]);

    workspaceDashboard($this, $this->member)->assertSee('1 application at Interview for Backend Developer');
});

test('a stage opened from the dashboard lists the applications that count there', function () {
    $job = JobPosting::factory()->for($this->company)->create();
    $open = Application::factory()->for($job)->create(['stage' => ApplicationStage::Interview]);
    $turnedDown = Application::factory()->for($job)->create(['stage' => ApplicationStage::Interview, 'outcome_status' => ApplicationOutcomeStatus::Rejected]);

    Livewire::withQueryParams(['stage' => 'interview'])
        ->actingAs($this->member)
        ->test('pages::employer.applications', ['company' => $this->company, 'jobPosting' => $job])
        ->assertSet('stageFilter', 'interview')
        ->assertSee($open->candidateProfile->user->name)
        ->assertDontSee($turnedDown->candidateProfile->user->name)
        ->set('stageFilter', 'all')
        ->assertSee($turnedDown->candidateProfile->user->name);
});

test('a posting has one state, checked in the order the company would ask', function () {
    $reported = JobPosting::factory()->for($this->company)->create();
    hideByReports($reported);

    expect(PostingState::of(JobPosting::factory()->for($this->company)->create()))->toBe(PostingState::Live)
        ->and(PostingState::of(JobPosting::factory()->for($this->company)->draft()->pendingModeration()->create()))->toBe(PostingState::Draft)
        ->and(PostingState::of(JobPosting::factory()->for($this->company)->closed()->pendingModeration()->create()))->toBe(PostingState::Closed)
        ->and(PostingState::of(JobPosting::factory()->for($this->company)->create(['expires_at' => now()->subMinute()])))->toBe(PostingState::Expired)
        ->and(PostingState::of(JobPosting::factory()->for($this->company)->pendingModeration()->create()))->toBe(PostingState::InReview)
        ->and(PostingState::of($reported))->toBe(PostingState::HiddenForReview);
});

test('a hidden posting is not called active in the list', function () {
    hideByReports(JobPosting::factory()->for($this->company)->create(['title' => 'Reported Role']));

    Livewire::actingAs($this->owner)
        ->test('pages::employer.job-listings', ['company' => $this->company])
        ->assertSeeInOrder(['Reported Role', 'Hidden for review'])
        ->assertDontSee('Active');
});

test('the postings list keeps its tab in the address', function () {
    JobPosting::factory()->for($this->company)->draft()->create(['title' => 'Parked Role']);
    JobPosting::factory()->for($this->company)->create(['title' => 'Open Role']);

    Livewire::withQueryParams(['status' => 'draft'])
        ->actingAs($this->owner)
        ->test('pages::employer.job-listings', ['company' => $this->company])
        ->assertSee('Parked Role')
        ->assertDontSee('Open Role');
});

test('a draft is published from the form, never reopened from the list', function () {
    $draft = JobPosting::factory()->for($this->company)->draft()->create(['title' => 'Parked Role']);

    Livewire::actingAs($this->owner)
        ->test('pages::employer.job-listings', ['company' => $this->company])
        ->assertDontSee('Reopen')
        ->call('reopen', $draft->id);

    expect($draft->fresh()->availability_status)->toBe(AvailabilityStatus::Draft);
});

test('a posting that is out offers no draft, and saving keeps it out', function () {
    $live = JobPosting::factory()->for($this->company)->create();

    fillJobForm(Livewire::actingAs($this->owner)->test('pages::employer.job-form', ['company' => $this->company, 'jobPosting' => $live]))
        ->assertDontSee('Save as draft')
        ->call('save')
        ->assertHasNoErrors();

    expect($live->fresh()->availability_status)->toBe(AvailabilityStatus::Active);
});

test('editing a closed posting leaves it closed', function () {
    $closed = JobPosting::factory()->for($this->company)->closed()->create();

    fillJobForm(Livewire::actingAs($this->owner)->test('pages::employer.job-form', ['company' => $this->company, 'jobPosting' => $closed]))
        ->call('saveAndPublish')
        ->assertHasNoErrors();

    expect($closed->fresh()->availability_status)->toBe(AvailabilityStatus::Closed);
});

test('a lapsed posting saved with a date ahead takes applications again', function () {
    $lapsed = JobPosting::factory()->for($this->company)->expired()->create();

    fillJobForm(Livewire::actingAs($this->owner)->test('pages::employer.job-form', ['company' => $this->company, 'jobPosting' => $lapsed]))
        ->call('saveAndPublish')
        ->assertHasNoErrors();

    expect($lapsed->fresh()->availability_status)->toBe(AvailabilityStatus::Active);
});

test('the preview shows the posting as candidates will, and saves nothing', function () {
    $laravel = Skill::create(['name' => 'Laravel']);
    $engineering = Category::create(['name' => 'Engineering', 'slug' => 'engineering']);

    fillJobForm(Livewire::actingAs($this->owner)->test('pages::employer.job-form', ['company' => $this->company]), [
        'description' => '<p>Build our platform.</p><script>alert(1)</script>',
        'salaryMin' => '60000',
        'salaryMax' => '75000',
        'salaryCurrency' => 'GBP',
        'salaryPeriod' => 'yearly',
        'categories' => [$engineering->id],
    ])
        ->call('addSkill', $laravel->id)
        ->call('showPreview')
        ->assertSeeInOrder(['Senior Laravel Developer', 'Fernhill Software', 'Build our platform.', 'Required', 'Laravel'])
        ->assertSee('£60,000–£75,000')
        ->assertSee('Engineering')
        ->assertDontSee('<script>alert(1)</script>', false);

    expect(JobPosting::count())->toBe(0);
});

test('a new posting starts on the pay period the company last used', function () {
    JobPosting::factory()->for($this->company)->create(['salary_period' => SalaryPeriod::Yearly]);

    Livewire::actingAs($this->owner)
        ->test('pages::employer.job-form', ['company' => $this->company])
        ->assertSet('salaryPeriod', 'yearly');
});

test('the closing date names the offset the company will have on that day', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-08 09:00', 'UTC'));

    Livewire::actingAs($this->owner)
        ->test('pages::employer.job-form', ['company' => $this->company])
        ->set('expiresAt', '2026-12-15')
        ->assertSee('Europe/London (GMT+00:00)')
        ->set('expiresAt', '2026-10-20')
        ->assertSee('Europe/London (GMT+01:00)');
});

test('company setup sits in the app frame with a way back', function () {
    $this->actingAs($this->owner)
        ->get(route('companies.create'))
        ->assertOk()
        ->assertSee('Set up your company')
        ->assertSee('Direct employer')
        ->assertSee('href="'.route('employer.dashboard', $this->company).'"', false);

    $this->actingAs(User::factory()->create())
        ->get(route('companies.create'))
        ->assertOk()
        ->assertSee('href="'.route('dashboard').'"', false);
});
