<?php

use App\Actions\ModerateCompanyReview;
use App\Actions\SubmitCompanyReview;
use App\Enums\ApplicationOutcomeStatus;
use App\Enums\JobAsDescribed;
use App\Enums\MembershipRole;
use App\Enums\ModerationAction;
use App\Enums\ModerationStatus;
use App\Enums\ResponseRejectionReason;
use App\Enums\ReviewRejectionReason;
use App\Filament\Resources\CompanyReviews\Pages\ManageCompanyReviews;
use App\Models\Application;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\JobPosting;
use App\Models\Membership;
use App\Models\ModerationEvent;
use App\Notifications\CompanyReviewResponsePublished;
use App\Notifications\ReviewResponseAwaitingReview;
use App\Notifications\ReviewResponseRejected;
use App\Support\SubmissionLimits;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00'));

    $this->company = Company::factory()->create(['name' => 'Acme Hiring']);
    $this->owner = employerUser($this->company, MembershipRole::Owner);
    $this->manager = employerUser($this->company, MembershipRole::Manager);
    $this->member = employerUser($this->company, MembershipRole::Member);
    $this->review = answerableReview($this->company, ['title' => 'Quick and fair', 'published_at' => '2026-04-10 09:00:00']);
    $this->staff = staffWithTwoFactor();
});

/**
 * A published review behind an application to a closed posting, so the
 * posting's title appears nowhere else.
 */
function answerableReview(Company $company, array $attributes = []): CompanyReview
{
    $application = Application::factory()
        ->for(JobPosting::factory()->for($company)->closed()->create(['title' => 'Night Shift Analyst']))
        ->create(['outcome_status' => ApplicationOutcomeStatus::Rejected, 'decided_at' => now()->subMonths(3)]);

    return CompanyReview::factory()->for($application)->published()->create($attributes);
}

function withResponse(CompanyReview $review, ModerationStatus $status, string $body = 'Thank you for telling us how it went.'): CompanyReview
{
    $review->forceFill([
        'response_body' => $body,
        'response_status' => $status,
        'responded_at' => now(),
    ])->save();

    return $review;
}

function reviewsPage($test, $user)
{
    return Livewire::actingAs($user)->test('pages::employer.reviews', ['company' => $test->company]);
}

test('every member sees the published reviews as the public does, and nothing more', function () {
    answerableReview($this->company, ['title' => 'Still being read', 'moderation_status' => ModerationStatus::Pending, 'published_at' => null]);
    answerableReview($this->company, ['title' => 'Kept off the page', 'moderation_status' => ModerationStatus::Rejected]);

    $this->actingAs($this->member)
        ->get(route('employer.reviews', $this->company))
        ->assertOk()
        ->assertSee(['Quick and fair', 'Verified applicant', 'April 2026'])
        ->assertSee('Owners and managers can answer reviews on behalf of the company.')
        ->assertDontSee(['Still being read', 'Kept off the page', 'Night Shift Analyst', 'Answer publicly'])
        ->assertDontSee($this->review->candidateProfile->user->name);
});

test('people outside the company cannot open it', function () {
    $this->actingAs(employerUser())
        ->get(route('employer.reviews', $this->company))
        ->assertForbidden();
});

test('an owner or manager answers, and the answer waits for staff before anyone sees it', function (string $who) {
    $insider = staffWithTwoFactor();
    Membership::factory()->for($insider)->for($this->company)->create(['role' => MembershipRole::Member]);

    reviewsPage($this, $this->{$who})
        ->call('startAnswer', $this->review->id)
        ->set('response', "  We are sorry the wait was long.\n\nWe now answer within a week.  ")
        ->call('saveAnswer')
        ->assertHasNoErrors();

    $review = $this->review->fresh();

    expect($review->response_status)->toBe(ModerationStatus::Pending)
        ->and($review->response_body)->toBe("We are sorry the wait was long.\n\nWe now answer within a week.")
        ->and($review->responded_by_id)->toBe($this->{$who}->id)
        ->and($review->responded_at->toDateTimeString())->toBe('2026-06-15 12:00:00');

    Notification::assertSentTo($this->staff, ReviewResponseAwaitingReview::class);
    Notification::assertNotSentTo($insider, ReviewResponseAwaitingReview::class);

    $this->get(route('companies.show', $this->company))
        ->assertSee('Quick and fair')
        ->assertDontSee('We are sorry the wait was long.');
})->with(['owner', 'manager']);

test('a plain member cannot answer, even by calling the action', function () {
    reviewsPage($this, $this->member)
        ->call('startAnswer', $this->review->id)
        ->assertForbidden();
});

test('only this company\'s published reviews can be answered from its page', function () {
    $elsewhere = answerableReview(Company::factory()->create());
    $waiting = answerableReview($this->company, ['moderation_status' => ModerationStatus::Pending, 'published_at' => null]);

    $page = reviewsPage($this, $this->owner);

    expect(fn () => $page->call('startAnswer', $elsewhere->id))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $page->call('startAnswer', $waiting->id))->toThrow(ModelNotFoundException::class);
});

test('an answer is required and runs to at most 5,000 characters', function () {
    reviewsPage($this, $this->owner)
        ->call('startAnswer', $this->review->id)
        ->set('response', '   ')
        ->call('saveAnswer')
        ->assertHasErrors(['response' => 'required'])
        ->set('response', str_repeat('a', 5001))
        ->call('saveAnswer')
        ->assertHasErrors(['response' => 'max']);

    expect($this->review->fresh()->response_status)->toBeNull();
});

test('changing an answer that is still waiting does not mail staff again', function () {
    $page = reviewsPage($this, $this->owner);

    foreach (['First try at an answer.', 'Second try at an answer.'] as $text) {
        $page->call('startAnswer', $this->review->id)->set('response', $text)->call('saveAnswer');
    }

    expect($this->review->fresh()->response_body)->toBe('Second try at an answer.');
    Notification::assertSentToTimes($this->staff, ReviewResponseAwaitingReview::class, 1);
});

test('answers are limited per company per day', function () {
    $key = SubmissionLimits::responseSaveKey($this->company);
    foreach (range(1, SubmissionLimits::RESPONSE_SAVES_PER_DAY) as $attempt) {
        RateLimiter::hit($key, 86400);
    }

    reviewsPage($this, $this->owner)
        ->call('startAnswer', $this->review->id)
        ->set('response', 'One answer too many.')
        ->call('saveAnswer');

    expect($this->review->fresh()->response_status)->toBeNull();
});

test('removing an answer leaves the review where it was', function () {
    withResponse($this->review, ModerationStatus::Approved);

    reviewsPage($this, $this->manager)
        ->call('confirmWithdraw', $this->review->id)
        ->call('withdraw');

    $review = $this->review->fresh();

    expect($review->response_body)->toBeNull()
        ->and($review->response_status)->toBeNull()
        ->and($review->responded_by_id)->toBeNull()
        ->and($review->moderation_status)->toBe(ModerationStatus::Approved);
});

test('the reviews waiting on the company are those with no answer or a rejected one', function () {
    withResponse(answerableReview($this->company), ModerationStatus::Rejected);
    withResponse(answerableReview($this->company), ModerationStatus::Pending);
    withResponse(answerableReview($this->company), ModerationStatus::Approved);

    reviewsPage($this, $this->owner)
        ->assertSee('Waiting for an answer (2)')
        ->set('show', 'waiting')
        ->assertSee('Quick and fair');

    $this->actingAs($this->owner)
        ->get(route('employer.dashboard', $this->company))
        ->assertSee('2 reviews of your hiring process are waiting for an answer.');

    $this->actingAs($this->member)
        ->get(route('employer.dashboard', $this->company))
        ->assertDontSee('waiting for an answer');
});

test('staff approve an answer: it appears under the review, and the writer is told', function () {
    withResponse($this->review, ModerationStatus::Pending, 'We now reply to everyone within a week.');
    $this->review->update(['responded_by_id' => $this->manager->id]);
    $this->actingAs($this->staff);

    Livewire::test(ManageCompanyReviews::class)
        ->set('activeTab', 'responses')
        ->assertCanSeeTableRecords([$this->review])
        ->callAction(TestAction::make('approveResponse')->table($this->review))
        ->assertHasNoActionErrors();

    expect($this->review->fresh()->response_status)->toBe(ModerationStatus::Approved)
        ->and(ModerationEvent::where('action', ModerationAction::ApproveReviewResponse)->count())->toBe(1);

    Notification::assertSentTo($this->review->candidateProfile->user, CompanyReviewResponsePublished::class);

    $this->get(route('companies.show', $this->company))
        ->assertSee('Response from Acme Hiring')
        ->assertSee('We now reply to everyone within a week.')
        ->assertDontSee($this->manager->name)
        ->assertDontSee('Written to an earlier version');
});

test('staff reject an answer on a closed list of grounds, and the owners and managers are told why', function () {
    withResponse($this->review, ModerationStatus::Pending, 'We know exactly who you are.');
    $this->actingAs($this->staff);

    Livewire::test(ManageCompanyReviews::class)
        ->set('activeTab', 'responses')
        ->callAction(TestAction::make('rejectResponse')->table($this->review), data: [
            'reason' => ResponseRejectionReason::IdentifiesWriter->value,
            'note' => 'Answer the points in the review instead.',
        ])
        ->assertHasNoActionErrors();

    $review = $this->review->fresh();
    $reason = ModerationEvent::where('action', ModerationAction::RejectReviewResponse)->sole()->reason;

    expect($review->response_status)->toBe(ModerationStatus::Rejected)
        ->and($review->moderation_status)->toBe(ModerationStatus::Approved)
        ->and($reason)->toContain(ResponseRejectionReason::IdentifiesWriter->forCompany())
        ->and($reason)->toContain('Answer the points in the review instead.');

    Notification::assertSentTo([$this->owner, $this->manager], ReviewResponseRejected::class);
    Notification::assertNotSentTo($this->member, ReviewResponseRejected::class);

    $this->actingAs($this->owner)
        ->get(route('employer.reviews', $this->company))
        ->assertSee('Our team did not publish this answer')
        ->assertSee('Answer the points in the review instead.');

    $this->get(route('companies.show', $this->company))->assertDontSee('We know exactly who you are.');
});

test('a published answer can be taken back down, and a rejected one approved', function () {
    $this->actingAs($this->staff);

    withResponse($this->review, ModerationStatus::Approved);
    Livewire::test(ManageCompanyReviews::class)
        ->set('activeTab', ModerationStatus::Approved->value)
        ->assertActionVisible(TestAction::make('rejectResponse')->table($this->review))
        ->assertActionHidden(TestAction::make('approveResponse')->table($this->review));

    withResponse($this->review, ModerationStatus::Rejected);
    Livewire::test(ManageCompanyReviews::class)
        ->set('activeTab', ModerationStatus::Approved->value)
        ->assertActionVisible(TestAction::make('approveResponse')->table($this->review))
        ->assertActionHidden(TestAction::make('rejectResponse')->table($this->review));
});

test('staff on the company\'s team never see its answers in the queue', function () {
    withResponse($this->review, ModerationStatus::Pending);
    $insider = staffWithTwoFactor();
    Membership::factory()->for($insider)->for($this->company)->create(['role' => MembershipRole::Member]);

    $this->actingAs($insider->fresh());

    Livewire::test(ManageCompanyReviews::class)
        ->set('activeTab', 'responses')
        ->assertCountTableRecords(0);
});

test('the review\'s decisions and the answer\'s decisions are kept apart in the panel', function () {
    withResponse($this->review, ModerationStatus::Pending);
    app(ModerateCompanyReview::class)->rejectResponse($this->review, $this->staff, ResponseRejectionReason::Unrelated);
    $this->actingAs($this->staff);

    Livewire::test(ManageCompanyReviews::class)
        ->set('activeTab', ModerationStatus::Approved->value)
        ->mountAction(TestAction::make('view')->table($this->review))
        ->assertMountedActionModalSee(['Never reviewed', 'Rejected company response to a review']);
});

test('when the writer rewrites the review, the answer stays and readers are told what it answered', function () {
    withResponse($this->review, ModerationStatus::Approved, 'Glad the process felt fair.');

    $this->travel(2)->days();
    app(SubmitCompanyReview::class)($this->review->application, [
        'overall_rating' => 2,
        'communication_rating' => 2,
        'job_as_described' => JobAsDescribed::No->value,
        'title' => 'Changed my mind',
        'body' => 'Looking back, the job turned out to be quite different from the posting, and nobody said so.',
    ]);

    $this->get(route('companies.show', $this->company))->assertDontSee('Glad the process felt fair.');

    $this->travel(1)->days();
    app(ModerateCompanyReview::class)->approve($this->review->fresh(), $this->staff);

    expect($this->review->fresh()->responseAnswersEarlierVersion())->toBeTrue();

    $this->get(route('companies.show', $this->company))
        ->assertSee('Changed my mind')
        ->assertSee('Glad the process felt fair.')
        ->assertSee('Written to an earlier version of this review.');

    $this->travel(1)->days();
    withResponse($this->review->fresh(), ModerationStatus::Approved, 'Sorry the job was not what we described.');

    expect($this->review->fresh()->responseAnswersEarlierVersion())->toBeFalse();
});

test('the writer sees the company\'s published answer on their own review', function () {
    withResponse($this->review, ModerationStatus::Approved, 'Thanks for the honest account.');
    $writer = $this->review->candidateProfile->user;

    $this->actingAs($writer)
        ->get(route('candidate.applications.show', $this->review->application_id))
        ->assertOk()
        ->assertSee('Response from Acme Hiring')
        ->assertSee('Thanks for the honest account.');
});

test('answers and staff decisions leave the time the review was written alone', function () {
    $written = $this->review->fresh()->updated_at;
    $this->travel(1)->days();

    reviewsPage($this, $this->owner)
        ->call('startAnswer', $this->review->id)
        ->set('response', 'Thanks for writing.')
        ->call('saveAnswer');

    $moderate = app(ModerateCompanyReview::class);
    $moderate->approveResponse($this->review->fresh(), $this->staff);
    $moderate->reject($this->review->fresh(), $this->staff, ReviewRejectionReason::Unrelated);
    $moderate->approve($this->review->fresh(), $this->staff);

    expect($this->review->fresh()->updated_at->equalTo($written))->toBeTrue();
});
