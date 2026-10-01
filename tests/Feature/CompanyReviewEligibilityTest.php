<?php

use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\ModerationStatus;
use App\Models\Application;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\JobPosting;
use App\Models\Membership;
use App\Support\ReviewEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-01 09:00:00'));

    $this->company = Company::factory()->create();
    $this->candidate = candidateUser();
    $this->application = Application::factory()
        ->for(JobPosting::factory()->for($this->company))
        ->create(['candidate_profile_id' => $this->candidate->candidateProfile->id]);
    $this->recruiter = employerUser($this->company, MembershipRole::Manager);
});

function reviewReason(Application $application): string
{
    return ReviewEligibility::of($application->fresh())->reason;
}

function moveStage(Application $application, ApplicationStage $to, $by): void
{
    $application->events()->create([
        'changed_by_id' => $by->id,
        'from_stage' => $application->stage->value,
        'to_stage' => $to->value,
    ]);
    $application->update(['stage' => $to]);
}

function decideOutcome(Application $application, ApplicationOutcomeStatus $to, $by, bool $undoWindowOver = true): void
{
    $application->events()->create([
        'changed_by_id' => $by->id,
        'from_outcome_status' => $application->outcome_status->value,
        'to_outcome_status' => $to->value,
    ]);
    $application->forceFill([
        'outcome_status' => $to,
        'decided_at' => $undoWindowOver ? now()->subMinutes(Application::UNDO_MINUTES + 1) : now(),
    ])->save();
}

function withdrawApplication(Application $application): void
{
    $application->events()->create([
        'changed_by_id' => $application->candidateProfile->user_id,
        'from_outcome_status' => $application->outcome_status->value,
        'to_outcome_status' => ApplicationOutcomeStatus::Withdrawn->value,
    ]);
    $application->update(['outcome_status' => ApplicationOutcomeStatus::Withdrawn]);
}

test('a hire or a rejection the candidate has been told about makes the application reviewable', function (ApplicationOutcomeStatus $outcome) {
    decideOutcome($this->application, $outcome, $this->recruiter);

    expect(reviewReason($this->application))->toBe(ReviewEligibility::DECIDED)
        ->and(ReviewEligibility::of($this->application->fresh())->allows())->toBeTrue();
})->with([ApplicationOutcomeStatus::Hired, ApplicationOutcomeStatus::Rejected]);

test('a decision still inside its undo window does not count yet', function () {
    decideOutcome($this->application, ApplicationOutcomeStatus::Rejected, $this->recruiter, undoWindowOver: false);

    expect(reviewReason($this->application))->toBe(ReviewEligibility::IN_PROGRESS);
});

test('reaching the interview stage makes it reviewable, now or at any point before', function () {
    moveStage($this->application, ApplicationStage::Interview, $this->recruiter);

    expect(reviewReason($this->application))->toBe(ReviewEligibility::INTERVIEWED);

    moveStage($this->application, ApplicationStage::Shortlisted, $this->recruiter);

    expect(reviewReason($this->application))->toBe(ReviewEligibility::INTERVIEWED);
});

test('an offer counts as beyond the interview', function () {
    moveStage($this->application, ApplicationStage::Offer, $this->recruiter);

    expect(reviewReason($this->application))->toBe(ReviewEligibility::INTERVIEWED);
});

test('withdrawing after an interview keeps the right to review it', function () {
    moveStage($this->application, ApplicationStage::Interview, $this->recruiter);
    withdrawApplication($this->application);

    expect(reviewReason($this->application))->toBe(ReviewEligibility::INTERVIEWED);
});

test('a new application with no reply yet is not reviewable', function () {
    expect(reviewReason($this->application))->toBe(ReviewEligibility::IN_PROGRESS);

    $this->travel(ReviewEligibility::UNANSWERED_DAYS - 1)->days();

    expect(reviewReason($this->application))->toBe(ReviewEligibility::IN_PROGRESS);
});

test('thirty days without any reply makes it reviewable', function () {
    $this->travel(ReviewEligibility::UNANSWERED_DAYS)->days();

    expect(reviewReason($this->application))->toBe(ReviewEligibility::UNANSWERED);
});

test('a reply within the month means the silence never happened', function () {
    $this->travel(5)->days();
    moveStage($this->application, ApplicationStage::Shortlisted, $this->recruiter);
    $this->travel(60)->days();

    expect(reviewReason($this->application))->toBe(ReviewEligibility::IN_PROGRESS);
});

test('a reply that only comes after the month cannot take the right away', function () {
    $this->travel(40)->days();
    moveStage($this->application, ApplicationStage::Shortlisted, $this->recruiter);

    expect(reviewReason($this->application))->toBe(ReviewEligibility::UNANSWERED);
});

test('a rejection that was undone is not a reply', function () {
    $this->travel(3)->days();
    decideOutcome($this->application, ApplicationOutcomeStatus::Rejected, $this->recruiter, undoWindowOver: false);
    $this->application->events()->create([
        'changed_by_id' => $this->recruiter->id,
        'from_outcome_status' => ApplicationOutcomeStatus::Rejected->value,
        'to_outcome_status' => ApplicationOutcomeStatus::Active->value,
    ]);
    $this->application->forceFill(['outcome_status' => ApplicationOutcomeStatus::Active, 'decided_at' => null])->save();
    $this->travel(30)->days();

    expect(reviewReason($this->application))->toBe(ReviewEligibility::UNANSWERED);
});

test('withdrawing before anything happened leaves nothing to review, however long ago', function () {
    $this->travel(3)->days();
    withdrawApplication($this->application);
    $this->travel(60)->days();

    expect(reviewReason($this->application))->toBe(ReviewEligibility::WITHDRAWN);
});

test('withdrawing after a reply but before an interview leaves nothing to review', function () {
    moveStage($this->application, ApplicationStage::Shortlisted, $this->recruiter);
    withdrawApplication($this->application);

    expect(reviewReason($this->application))->toBe(ReviewEligibility::WITHDRAWN);
});

test('giving up after a month of silence still counts as that silence', function () {
    $this->travel(35)->days();
    withdrawApplication($this->application);

    expect(reviewReason($this->application))->toBe(ReviewEligibility::UNANSWERED);
});

test('anyone who is or ever was on the company team cannot review it', function (MembershipStatus $status) {
    Membership::factory()->for($this->candidate)->for($this->company)->create(['status' => $status]);
    decideOutcome($this->application, ApplicationOutcomeStatus::Rejected, $this->recruiter);

    expect(reviewReason($this->application))->toBe(ReviewEligibility::INSIDER);
})->with([MembershipStatus::Active, MembershipStatus::Inactive]);

test('working for a different company does not stop a review', function () {
    Membership::factory()->for($this->candidate)->create();
    decideOutcome($this->application, ApplicationOutcomeStatus::Rejected, $this->recruiter);

    expect(reviewReason($this->application))->toBe(ReviewEligibility::DECIDED);
});

test('one application gives one review', function () {
    decideOutcome($this->application, ApplicationOutcomeStatus::Rejected, $this->recruiter);
    CompanyReview::factory()->for($this->application)->create();

    expect(reviewReason($this->application))->toBe(ReviewEligibility::ALREADY_REVIEWED)
        ->and(fn () => CompanyReview::factory()->for($this->application)->create())->toThrow(QueryException::class);
});

test('a second application to the same company does not give a second review', function () {
    decideOutcome($this->application, ApplicationOutcomeStatus::Rejected, $this->recruiter);
    CompanyReview::factory()->for($this->application)->create();

    $second = Application::factory()
        ->for(JobPosting::factory()->for($this->company))
        ->create(['candidate_profile_id' => $this->candidate->candidateProfile->id]);
    decideOutcome($second, ApplicationOutcomeStatus::Rejected, $this->recruiter);

    expect(reviewReason($second))->toBe(ReviewEligibility::ALREADY_REVIEWED)
        ->and($this->candidate->can('create', [CompanyReview::class, $second->fresh()]))->toBeFalse()
        ->and(fn () => CompanyReview::factory()->for($second)->create())->toThrow(QueryException::class);
});

test('a review of one company does not use up a review of another', function () {
    decideOutcome($this->application, ApplicationOutcomeStatus::Rejected, $this->recruiter);
    CompanyReview::factory()->for($this->application)->create();

    $elsewhere = Application::factory()
        ->create(['candidate_profile_id' => $this->candidate->candidateProfile->id]);
    decideOutcome($elsewhere, ApplicationOutcomeStatus::Rejected, $this->recruiter);

    expect(reviewReason($elsewhere))->toBe(ReviewEligibility::DECIDED);
});

test('a review that staff rejected still counts as the one review', function () {
    decideOutcome($this->application, ApplicationOutcomeStatus::Rejected, $this->recruiter);
    CompanyReview::factory()->for($this->application)->create(['moderation_status' => ModerationStatus::Rejected]);

    $second = Application::factory()
        ->for(JobPosting::factory()->for($this->company))
        ->create(['candidate_profile_id' => $this->candidate->candidateProfile->id]);
    decideOutcome($second, ApplicationOutcomeStatus::Rejected, $this->recruiter);

    expect(reviewReason($second))->toBe(ReviewEligibility::ALREADY_REVIEWED);
});

test('a review belongs to the company the application leads to, and waits for staff', function () {
    $review = CompanyReview::factory()->create();

    expect($review->company_id)->toBe($review->application->jobPosting->company_id)
        ->and($review->candidate_profile_id)->toBe($review->application->candidate_profile_id)
        ->and($review->moderation_status)->toBe(ModerationStatus::Pending)
        ->and($review->published_at)->toBeNull()
        ->and($review->company->reviews->sole()->is($review))->toBeTrue()
        ->and($review->application->review->is($review))->toBeTrue();
});

test('only the candidate behind an eligible application can write the review', function () {
    decideOutcome($this->application, ApplicationOutcomeStatus::Rejected, $this->recruiter);
    $application = $this->application->fresh();

    expect($this->candidate->can('create', [CompanyReview::class, $application]))->toBeTrue()
        ->and(candidateUser()->can('create', [CompanyReview::class, $application]))->toBeFalse()
        ->and($this->recruiter->can('create', [CompanyReview::class, $application]))->toBeFalse();
});

test('an application that does not qualify cannot be reviewed by its own candidate', function () {
    expect($this->candidate->can('create', [CompanyReview::class, $this->application->fresh()]))->toBeFalse();
});

test('the writer can edit and delete their review; nobody else can', function () {
    $review = CompanyReview::factory()->published()->for($this->application)->create();
    $owner = employerUser($this->company, MembershipRole::Owner);

    expect($this->candidate->can('update', $review))->toBeTrue()
        ->and($this->candidate->can('delete', $review))->toBeTrue()
        ->and($owner->can('update', $review))->toBeFalse()
        ->and($owner->can('delete', $review))->toBeFalse()
        ->and(candidateUser()->can('delete', $review))->toBeFalse()
        ->and(staffUser()->can('delete', $review))->toBeFalse();
});

test('a writer who later joins the company keeps the review but can no longer rewrite it', function () {
    $review = CompanyReview::factory()->published()->for($this->application)->create();
    Membership::factory()->for($this->candidate)->for($this->company)->create();

    expect($this->candidate->can('update', $review->fresh()))->toBeFalse()
        ->and($this->candidate->can('delete', $review->fresh()))->toBeTrue();
});

test('an owner or manager answers a published review; a member cannot', function (MembershipRole $role, bool $allowed) {
    $review = CompanyReview::factory()->published()->for($this->application)->create();

    expect(employerUser($this->company, $role)->can('respond', $review))->toBe($allowed);
})->with([
    'owner' => [MembershipRole::Owner, true],
    'manager' => [MembershipRole::Manager, true],
    'member' => [MembershipRole::Member, false],
]);

test('no answer to a review that is not public, or from another company', function () {
    $pending = CompanyReview::factory()->for($this->application)->create();
    $published = CompanyReview::factory()->published()->create();

    expect($this->recruiter->can('respond', $pending))->toBeFalse()
        ->and($this->recruiter->can('respond', $published))->toBeFalse();
});

test('a manager who has left the company cannot answer for it', function () {
    $review = CompanyReview::factory()->published()->for($this->application)->create();
    $this->recruiter->memberships()->update(['status' => MembershipStatus::Inactive]);

    expect($this->recruiter->can('respond', $review))->toBeFalse();
});

test('a report on a review leads back to its company, so staff from that company step aside', function () {
    $review = CompanyReview::factory()->published()->for($this->application)->create();
    $report = $review->reports()->create(['reporter_id' => $this->recruiter->id, 'reason' => 'Names our interviewer.']);
    $insiderStaff = staffUser();
    Membership::factory()->for($insiderStaff)->for($this->company)->create();

    expect($report->subjectCompany()->is($this->company))->toBeTrue()
        ->and($insiderStaff->can('moderate', $report))->toBeFalse()
        ->and(staffUser()->can('moderate', $report))->toBeTrue();
});
