<?php

use App\Actions\DismissReports;
use App\Enums\AccountStatus;
use App\Enums\MembershipRole;
use App\Enums\ModerationStatus;
use App\Enums\ReportStatus;
use App\Livewire\ReportButton;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Report;
use App\Models\User;
use Livewire\Livewire;

function reportAs(User $user, $subject): void
{
    Livewire::actingAs($user)
        ->test(ReportButton::class, ['reportable' => $subject])
        ->set('reason', 'scam')
        ->call('submit')
        ->assertHasNoErrors();
}

function reportByDifferentPeople($subject, int $people): void
{
    foreach (range(1, $people) as $ignored) {
        reportAs(User::factory()->create(), $subject);
    }
}

test('the report button files a report against a posting and a company', function () {
    $posting = JobPosting::factory()->create();

    reportAs(User::factory()->create(), $posting);
    reportAs(User::factory()->create(), $posting->company);

    expect($posting->reports()->sole()->review_status)->toBe(ReportStatus::Pending)
        ->and($posting->company->reports()->count())->toBe(1);
});

test('the same person reporting again adds nothing', function () {
    $posting = JobPosting::factory()->create();
    $reporter = User::factory()->create();

    reportAs($reporter, $posting);
    reportAs($reporter, $posting);

    expect($posting->reports()->count())->toBe(1);
});

test('a posting leaves public view once enough different people report it', function () {
    $posting = JobPosting::factory()->create(['title' => 'Too Good To Be True']);

    reportByDifferentPeople($posting, Report::HIDE_AFTER_REPORTERS - 1);
    $this->get('/')->assertSee('Too Good To Be True');

    reportByDifferentPeople($posting, 1);

    $this->get('/')->assertDontSee('Too Good To Be True');
    $this->get(route('jobs.show', $posting))->assertNotFound();
    // Hidden, not re-queued: its moderation decision is left alone.
    expect($posting->fresh()->moderation_status)->toBe(ModerationStatus::Approved);
});

test('one account reporting over and over cannot hide a posting', function () {
    $posting = JobPosting::factory()->create();
    $reporter = User::factory()->create();

    foreach (range(1, Report::HIDE_AFTER_REPORTERS) as $ignored) {
        $posting->reports()->create(['reporter_id' => $reporter->id, 'reason' => 'Spam']);
    }

    expect($posting->isHiddenByReports())->toBeFalse();
});

test('dismissing the reports brings a hidden posting back', function () {
    $posting = JobPosting::factory()->create();
    reportByDifferentPeople($posting, Report::HIDE_AFTER_REPORTERS);

    app(DismissReports::class)($posting->reports()->first(), staffWithTwoFactor());

    expect(JobPosting::query()->active()->whereKey($posting->id)->exists())->toBeTrue();
});

test('its own company still sees the hidden posting, marked as hidden', function () {
    $posting = JobPosting::factory()->create();
    reportByDifferentPeople($posting, Report::HIDE_AFTER_REPORTERS);

    Livewire::actingAs(employerUser($posting->company, MembershipRole::Owner))
        ->test('pages::employer.job-listings', ['company' => $posting->company])
        ->assertSee('Hidden for review');
});

test('a company reported by enough people disappears along with its postings', function () {
    $company = Company::factory()->create();
    $posting = JobPosting::factory()->for($company)->create(['title' => 'Company Role']);

    reportByDifferentPeople($company, Report::HIDE_AFTER_REPORTERS);

    $this->get(route('companies.show', $company))->assertNotFound();
    $this->get(route('jobs.show', $posting))->assertNotFound();
    $this->get('/')->assertDontSee('Company Role');
    expect(JobPosting::query()->active()->whereKey($posting->id)->exists())->toBeFalse();
    $this->get('/sitemap.xml')->assertDontSee(route('companies.show', $company));

    $this->actingAs(employerUser($company, MembershipRole::Member))
        ->get(route('companies.show', $company))
        ->assertOk();
});

test('a banned company has no public profile', function () {
    $company = Company::factory()->create();
    $company->account_status = AccountStatus::Suspended;
    $company->save();

    $this->get(route('companies.show', $company))->assertNotFound();
});

test('staff can see which reported things are hidden', function () {
    $posting = JobPosting::factory()->create();
    reportByDifferentPeople($posting, Report::HIDE_AFTER_REPORTERS);

    $this->actingAs(staffWithTwoFactor())
        ->get('/admin/moderation/reports')
        ->assertSee('Hidden from the public');
});

test('a verified company is marked on its job cards for visitors', function () {
    $company = Company::factory()->create(['verified_at' => now()]);
    JobPosting::factory()->for($company)->create();

    $this->get('/')->assertSee('Verified company');
});

test('after staff clear a posting, one more report does not hide it again', function () {
    $posting = JobPosting::factory()->create();
    reportByDifferentPeople($posting, Report::HIDE_AFTER_REPORTERS);
    app(DismissReports::class)($posting->reports()->first(), staffWithTwoFactor());

    reportByDifferentPeople($posting, 1);

    expect($posting->isHiddenByReports())->toBeFalse();
});

test('a guest cannot file a report even by calling the action directly', function () {
    $posting = JobPosting::factory()->create();

    Livewire::test(ReportButton::class, ['reportable' => $posting])
        ->set('reason', 'scam')
        ->call('submit')
        ->assertForbidden();

    expect($posting->reports()->count())->toBe(0);
});

test('a hidden posting\'s apply page answers 404 like the posting itself', function () {
    $posting = JobPosting::factory()->create();
    reportByDifferentPeople($posting, Report::HIDE_AFTER_REPORTERS);

    $this->actingAs(candidateUser())
        ->get(route('jobs.apply', $posting))
        ->assertNotFound();
});

test('saved jobs and recently viewed only show what is still public', function () {
    $candidate = candidateUser();
    $visible = JobPosting::factory()->create(['title' => 'Still Open Role']);
    $hidden = JobPosting::factory()->create(['title' => 'Rewritten Scam Role']);
    $candidate->savedJobs()->attach([$visible->id, $hidden->id]);

    $hidden->moderation_status = ModerationStatus::Pending;
    $hidden->save();

    $this->actingAs($candidate)
        ->get(route('candidate.saved-jobs.index'))
        ->assertSee('Still Open Role')
        ->assertDontSee('Rewritten Scam Role')
        ->assertSee('One job you saved is no longer available');
});

test('one account cannot send reports without limit', function () {
    $reporter = User::factory()->create();
    $postings = JobPosting::factory()->count(ReportButton::REPORTS_PER_HOUR + 1)->create();

    $postings->take(ReportButton::REPORTS_PER_HOUR)->each(fn ($posting) => reportAs($reporter, $posting));

    Livewire::actingAs($reporter)
        ->test(ReportButton::class, ['reportable' => $postings->last()])
        ->set('reason', 'scam')
        ->call('submit')
        ->assertHasErrors('reason');

    expect(Report::query()->count())->toBe(ReportButton::REPORTS_PER_HOUR);
});
