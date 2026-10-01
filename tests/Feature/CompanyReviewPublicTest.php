<?php

use App\Enums\ApplicationOutcomeStatus;
use App\Enums\JobAsDescribed;
use App\Enums\MembershipRole;
use App\Enums\ModerationAction;
use App\Enums\ModerationStatus;
use App\Enums\ReportStatus;
use App\Filament\Resources\Reports\Pages\ManageReports;
use App\Livewire\ReportButton;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\JobPosting;
use App\Models\Membership;
use App\Models\ModerationEvent;
use App\Models\Report;
use App\Models\User;
use App\Notifications\CompanyReviewRejected;
use App\Support\ReviewSummary;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));
    $this->company = Company::factory()->create(['name' => 'Acme Hiring']);
    $this->job = JobPosting::factory()->for($this->company)->create(['title' => 'Support Lead']);
});

/**
 * A published review, behind an application to a closed posting, so that
 * the posting's title is nowhere else on the company page.
 */
function publicReview(Company $company, array $attributes = [], ?CandidateProfile $writer = null): CompanyReview
{
    $application = Application::factory()
        ->for(JobPosting::factory()->for($company)->closed()->create(['title' => 'Night Shift Analyst']))
        ->create([
            'outcome_status' => ApplicationOutcomeStatus::Hired,
            'decided_at' => now()->subMonths(4),
            ...($writer ? ['candidate_profile_id' => $writer->id] : []),
        ]);

    return CompanyReview::factory()->for($application)->published()->create($attributes);
}

function reportReview(CompanyReview $review, string $reason = 'Identifies a person'): Report
{
    return $review->reports()->create([
        'reporter_id' => User::factory()->create()->id,
        'reason' => $reason,
    ]);
}

test('the company page shows published reviews only, as a verified applicant and a month', function () {
    $review = publicReview($this->company, ['title' => 'Fair and quick', 'published_at' => '2026-03-14 10:00:00']);
    publicReview($this->company, ['title' => 'Still being read', 'moderation_status' => ModerationStatus::Pending, 'published_at' => null]);
    publicReview($this->company, ['title' => 'Kept off the page', 'moderation_status' => ModerationStatus::Rejected]);

    $this->get(route('companies.show', $this->company))
        ->assertOk()
        ->assertSee('Hiring process reviews')
        ->assertSee('Fair and quick')
        ->assertSee('Verified applicant')
        ->assertSee('March 2026')
        ->assertDontSee('2026-03-14')
        ->assertDontSee('Still being read')
        ->assertDontSee('Kept off the page')
        ->assertDontSee($review->candidateProfile->user->name)
        ->assertDontSee('Night Shift Analyst')
        ->assertDontSee('Hired');
});

test('reviews come newest first, ten to a page', function () {
    foreach (range(1, 11) as $day) {
        publicReview($this->company, ['title' => "Review number {$day}", 'published_at' => sprintf('2026-05-%02d 09:00:00', $day)]);
    }

    $this->get(route('companies.show', $this->company))
        ->assertSeeInOrder(['Review number 11', 'Review number 10', 'Review number 2'])
        ->assertDontSee('Review number 1<', false);

    $this->get(route('companies.show', ['company' => $this->company, 'page' => 2]))
        ->assertSee('Review number 1')
        ->assertDontSee('Review number 11');
});

test('averages wait for three published reviews', function () {
    publicReview($this->company, ['overall_rating' => 5, 'communication_rating' => 3, 'job_as_described' => JobAsDescribed::Yes]);
    publicReview($this->company, ['overall_rating' => 4, 'communication_rating' => 3, 'job_as_described' => JobAsDescribed::Yes]);

    expect(ReviewSummary::of($this->company))
        ->count->toBe(2)
        ->overall->toBeNull()
        ->asDescribed->toBeNull();

    $this->get(route('companies.show', $this->company))
        ->assertSee('2 reviews so far. Averages appear once there are 3.')
        ->assertDontSee('Based on');

    publicReview($this->company, ['overall_rating' => 2, 'communication_rating' => 4, 'job_as_described' => JobAsDescribed::No]);
    publicReview($this->company, ['moderation_status' => ModerationStatus::Pending, 'published_at' => null, 'overall_rating' => 1]);

    expect(ReviewSummary::of($this->company))
        ->count->toBe(3)
        ->overall->toBe(3.7)
        ->communication->toBe(3.3)
        ->asDescribed->toBe(2);

    $this->get(route('companies.show', $this->company))
        ->assertSee(['3.7', '3.3', 'of 3 said yes', 'Based on 3 published reviews.'])
        ->assertDontSee('Averages appear once');
});

test('with no reviews the page says how they come to be', function () {
    $this->get(route('companies.show', $this->company))
        ->assertSee('No reviews yet.');
});

test('a review is shown as the text it is, never as markup', function () {
    publicReview($this->company, ['body' => 'They said <b>yes</b> and then went quiet for a month, which was hard to read.']);

    $this->get(route('companies.show', $this->company))
        ->assertSee('&lt;b&gt;yes&lt;/b&gt;', false)
        ->assertDontSee('<b>yes</b>', false);
});

test('the job page links to the company\'s reviews, with the average once there is one', function () {
    $this->get(route('jobs.show', $this->job))
        ->assertDontSee('hiring process review');

    publicReview($this->company, ['overall_rating' => 4]);

    $this->get(route('jobs.show', $this->job))
        ->assertSee('1 hiring process review')
        ->assertSee(route('companies.show', $this->company).'#reviews')
        ->assertDontSee('out of 5');

    publicReview($this->company, ['overall_rating' => 4]);
    publicReview($this->company, ['overall_rating' => 5]);

    $this->get(route('jobs.show', $this->job))
        ->assertSee('3 hiring process reviews')
        ->assertSee('4.3 out of 5');
});

test('a signed-in reader reports a review on one of the grounds a review can be taken down for', function () {
    $review = publicReview($this->company);
    $this->actingAs(candidateUser());

    Livewire::test(ReportButton::class, ['reportable' => $review])
        ->assertSee('Report this review')
        ->assertSee('The review stays up while our team reads it')
        ->set('reason', 'other')
        ->call('submit')
        ->assertHasErrors('reason')
        ->set('reason', 'personal_information')
        ->call('submit')
        ->assertHasNoErrors();

    expect($review->reports()->sole())
        ->reason->toBe('Identifies a person')
        ->review_status->toBe(ReportStatus::Pending);
});

test('guests are not offered a report button on reviews', function () {
    publicReview($this->company);

    $this->get(route('companies.show', $this->company))->assertDontSee('Report this review');

    $this->actingAs(candidateUser())
        ->get(route('companies.show', $this->company))
        ->assertSee('Report this review');
});

test('reports never take a review down on their own', function () {
    $review = publicReview($this->company, ['title' => 'Many people disliked this']);

    foreach (range(1, Report::HIDE_AFTER_REPORTERS + 1) as $reporter) {
        reportReview($review);
    }

    $this->get(route('companies.show', $this->company))->assertSee('Many people disliked this');
    expect(ReviewSummary::of($this->company)->count)->toBe(1);
});

test('the reports queue lists a reported review as a review of its company', function () {
    reportReview(publicReview($this->company));
    $this->actingAs(staffWithTwoFactor());

    Livewire::test(ManageReports::class)
        ->assertCountTableRecords(1)
        ->assertSee('Review of Acme Hiring')
        ->assertSee('Company review');
});

test('staff take a reported review down through the same closed list of grounds as the review queue', function () {
    $review = publicReview($this->company, ['title' => 'Names the recruiter']);
    $latest = reportReview($review);
    $this->actingAs(staffWithTwoFactor());

    Livewire::test(ManageReports::class)
        ->callAction(TestAction::make('rejectReview')->table($latest), data: ['reason' => 'personal_information', 'note' => 'Leave the name out.'])
        ->assertHasNoActionErrors();

    expect($review->fresh()->moderation_status)->toBe(ModerationStatus::Rejected)
        ->and($latest->fresh()->review_status)->toBe(ReportStatus::Actioned)
        ->and(ModerationEvent::where('action', ModerationAction::RejectCompanyReview)->sole()->reason)->toContain('Leave the name out.');

    Notification::assertSentTo($review->candidateProfile->user, CompanyReviewRejected::class);

    $this->get(route('companies.show', $this->company))->assertDontSee('Names the recruiter');
});

test('staff on the company\'s team see only what the public sees of a reported review, and cannot act on it', function () {
    $review = publicReview($this->company, ['title' => 'Slow but polite']);
    $latest = reportReview($review);

    $staff = staffWithTwoFactor();
    Membership::factory()->for($staff)->for($this->company)->create(['role' => MembershipRole::Member]);
    $this->actingAs($staff->fresh());

    Livewire::test(ManageReports::class)
        ->assertActionHidden(TestAction::make('dismiss')->table($latest))
        ->assertActionHidden(TestAction::make('rejectReview')->table($latest))
        ->mountAction(TestAction::make('view')->table($latest))
        ->assertMountedActionModalSee(['Review of Acme Hiring', 'Slow but polite'])
        ->assertMountedActionModalDontSee(['Night Shift Analyst', $review->candidateProfile->user->name]);
});

test('a staff member who wrote a review cannot rule on reports about it', function () {
    $staff = staffWithTwoFactor();
    $writer = CandidateProfile::factory()->for($staff)->create();
    $latest = reportReview(publicReview($this->company, writer: $writer));
    $this->actingAs($staff->fresh());

    expect($staff->fresh()->can('moderate', $latest))->toBeFalse()
        ->and(staffWithTwoFactor()->can('moderate', $latest))->toBeTrue();

    Livewire::test(ManageReports::class)
        ->assertActionHidden(TestAction::make('dismiss')->table($latest))
        ->assertActionHidden(TestAction::make('rejectReview')->table($latest));
});
