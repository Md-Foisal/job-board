<?php

use App\Actions\ModerateCompanyReview;
use App\Actions\SubmitCompanyReview;
use App\Enums\ApplicationOutcomeStatus;
use App\Enums\MembershipRole;
use App\Enums\ModerationAction;
use App\Enums\ModerationStatus;
use App\Enums\ReportStatus;
use App\Enums\ReviewRejectionReason;
use App\Filament\Resources\CompanyReviews\Pages\ManageCompanyReviews;
use App\Filament\Widgets\ModerationQueuesOverview;
use App\Models\Application;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\JobPosting;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\CompanyReviewApproved;
use App\Notifications\CompanyReviewAwaitingReview;
use App\Notifications\CompanyReviewPublished;
use App\Notifications\CompanyReviewRejected;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-06-01 09:00:00'));

    $this->company = Company::factory()->create(['name' => 'Acme Hiring']);
    $this->writer = candidateUser();
    $this->application = Application::factory()
        ->for(JobPosting::factory()->for($this->company)->create(['title' => 'Backend Developer']))
        ->create([
            'candidate_profile_id' => $this->writer->candidateProfile->id,
            'outcome_status' => ApplicationOutcomeStatus::Rejected,
        ]);
    $this->review = CompanyReview::factory()->for($this->application)->create(['title' => 'Slow but polite']);
    $this->staff = staffWithTwoFactor();
});

test('staff see the waiting reviews, with the company but never the writer', function () {
    $this->actingAs($this->staff)
        ->get('/admin/moderation/reviews')
        ->assertOk()
        ->assertSee('Slow but polite')
        ->assertSee('Acme Hiring')
        ->assertDontSee($this->writer->name);
});

test('the queue is closed to anyone who is not staff', function () {
    $this->actingAs($this->writer)
        ->get('/admin/moderation/reviews')
        ->assertForbidden();
});

test('the review page shows staff the proof behind it', function () {
    $this->actingAs($this->staff);

    Livewire::test(ManageCompanyReviews::class)
        ->mountAction(TestAction::make('view')->table($this->review))
        ->assertMountedActionModalSee(['Told of a hire or rejection', 'Backend Developer', 'Slow but polite'])
        ->assertMountedActionModalDontSee($this->writer->name);
});

test('approving publishes the review, records it, closes reports and tells the writer and the company', function () {
    $owner = employerUser($this->company, MembershipRole::Owner);
    $member = employerUser($this->company, MembershipRole::Member);
    $report = $this->review->reports()->create(['reporter_id' => User::factory()->create()->id, 'reason' => 'Unfair']);
    $this->actingAs($this->staff);

    Livewire::test(ManageCompanyReviews::class)
        ->callAction(TestAction::make('approve')->table($this->review))
        ->assertHasNoActionErrors();

    $review = $this->review->fresh();
    $event = $review->moderationEvents()->sole();

    expect($review->moderation_status)->toBe(ModerationStatus::Approved)
        ->and($review->published_at->equalTo(now()))->toBeTrue()
        ->and($event->action)->toBe(ModerationAction::ApproveCompanyReview)
        ->and($event->admin_id)->toBe($this->staff->id)
        ->and($report->fresh()->review_status)->toBe(ReportStatus::Reviewed);

    Notification::assertSentTo($this->writer, CompanyReviewApproved::class);
    Notification::assertSentTo($owner, CompanyReviewPublished::class, function ($notification) use ($owner) {
        $mail = implode(' ', $notification->toMail($owner)->introLines);

        return ! str_contains($mail, $this->writer->name) && ! str_contains($mail, 'Backend Developer');
    });
    Notification::assertNotSentTo($member, CompanyReviewPublished::class);
});

test('a reject needs one of the listed reasons', function () {
    $this->actingAs($this->staff);

    Livewire::test(ManageCompanyReviews::class)
        ->callAction(TestAction::make('reject')->table($this->review), data: ['reason' => null])
        ->assertHasActionErrors(['reason' => 'required']);

    Livewire::test(ManageCompanyReviews::class)
        ->callAction(TestAction::make('reject')->table($this->review), data: ['reason' => 'too_negative'])
        ->assertHasActionErrors(['reason']);

    expect($this->review->fresh()->moderation_status)->toBe(ModerationStatus::Pending);
});

test('rejecting keeps the review, records why, closes reports and tells only the writer', function () {
    $owner = employerUser($this->company, MembershipRole::Owner);
    $report = $this->review->reports()->create(['reporter_id' => User::factory()->create()->id, 'reason' => 'Names our recruiter']);
    $this->actingAs($this->staff);

    Livewire::test(ManageCompanyReviews::class)
        ->callAction(TestAction::make('reject')->table($this->review), data: [
            'reason' => ReviewRejectionReason::PersonalInformation->value,
            'note' => 'Please leave out the recruiter\'s first name.',
        ])
        ->assertHasNoActionErrors();

    $review = $this->review->fresh();
    $event = $review->moderationEvents()->sole();
    $expected = ReviewRejectionReason::PersonalInformation->forWriter()."\n\nPlease leave out the recruiter's first name.";

    expect($review)->not->toBeNull()
        ->and($review->moderation_status)->toBe(ModerationStatus::Rejected)
        ->and($event->action)->toBe(ModerationAction::RejectCompanyReview)
        ->and($event->reason)->toBe($expected)
        ->and($report->fresh()->review_status)->toBe(ReportStatus::Actioned);

    Notification::assertSentTo($this->writer, CompanyReviewRejected::class, fn ($notification) => $notification->reason === $expected);
    Notification::assertNotSentTo($owner, CompanyReviewPublished::class);

    $this->actingAs($this->writer)
        ->get(route('candidate.applications.show', $this->application))
        ->assertSee('Our team did not publish this review')
        ->assertSee('Please leave out the recruiter&#039;s first name.', false);
});

test('a published review can be taken down after a report, and put back', function () {
    app(ModerateCompanyReview::class)->approve($this->review, $this->staff);
    $firstPublished = $this->review->fresh()->published_at;

    $this->travel(3)->days();
    app(ModerateCompanyReview::class)->reject($this->review->fresh(), $this->staff, ReviewRejectionReason::Abusive);
    $this->travel(1)->day();
    app(ModerateCompanyReview::class)->approve($this->review->fresh(), $this->staff);

    expect($this->review->fresh()->published_at->equalTo($firstPublished))->toBeTrue();
});

test('an edited review is dated by the approval of its new text', function () {
    app(ModerateCompanyReview::class)->approve($this->review, $this->staff);

    $this->travel(8)->months();
    app(SubmitCompanyReview::class)($this->application, [
        'overall_rating' => 4,
        'communication_rating' => 4,
        'job_as_described' => 'yes',
        'title' => 'Better the second time',
        'body' => str_repeat('They replied within a week this time. ', 3),
    ]);

    expect($this->review->fresh()->published_at)->toBeNull();

    app(ModerateCompanyReview::class)->approve($this->review->fresh(), $this->staff);

    expect($this->review->fresh()->published_at->format('Y-m'))->toBe('2027-02');
});

test('staff step aside from reviews of their own employer and from their own reviews', function () {
    $insider = staffWithTwoFactor();
    Membership::factory()->for($insider)->for($this->company)->create();

    $this->actingAs($insider->fresh());
    Livewire::test(ManageCompanyReviews::class)
        ->assertActionHidden(TestAction::make('approve')->table($this->review))
        ->assertActionHidden(TestAction::make('reject')->table($this->review));

    $writerStaff = $this->writer;
    $writerStaff->forceFill(['staff_role' => $this->staff->staff_role, 'two_factor_confirmed_at' => now(), 'two_factor_secret' => encrypt('secret')])->save();

    expect($writerStaff->fresh()->can('moderate', $this->review))->toBeFalse()
        ->and($this->staff->can('moderate', $this->review))->toBeTrue();
});

test('the moderation log names the company a review was about, not its writer', function () {
    app(ModerateCompanyReview::class)->reject($this->review, $this->staff, ReviewRejectionReason::Unrelated);

    $this->actingAs($this->staff)
        ->get('/admin/moderation/log')
        ->assertOk()
        ->assertSee('Review of Acme Hiring')
        ->assertDontSee($this->writer->name);
});

test('the queue mail points at the review queue', function () {
    $notification = new CompanyReviewAwaitingReview($this->review);

    expect($notification->toMail($this->staff)->actionUrl)->toEndWith('/admin/moderation/reviews');
});

test('the staff dashboard counts the reviews waiting, oldest first', function () {
    $this->travel(30)->hours();

    $this->actingAs($this->staff);

    Livewire::test(ModerationQueuesOverview::class)
        ->assertSee('Company reviews to check')
        ->assertSee('Oldest waiting 1 day');
});
