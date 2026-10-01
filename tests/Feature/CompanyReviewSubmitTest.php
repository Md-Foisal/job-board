<?php

use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;
use App\Enums\JobAsDescribed;
use App\Enums\MembershipRole;
use App\Enums\ModerationStatus;
use App\Models\Application;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\JobPosting;
use App\Models\Membership;
use App\Notifications\CompanyReviewAwaitingReview;
use App\Support\ReviewTextFlags;
use App\Support\SubmissionLimits;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-06-01 09:00:00'));

    $this->company = Company::factory()->create(['name' => 'Acme Hiring']);
    $this->candidate = candidateUser();
    $this->application = rejectedApplication($this, 'Backend Developer');
});

function rejectedApplication($test, string $title): Application
{
    $application = Application::factory()
        ->for(JobPosting::factory()->for($test->company)->create(['title' => $title]))
        ->create(['candidate_profile_id' => $test->candidate->candidateProfile->id]);

    $application->forceFill([
        'outcome_status' => ApplicationOutcomeStatus::Rejected,
        'decided_at' => now()->subHour(),
    ])->save();

    return $application;
}

function reviewForm($test, ?Application $application = null)
{
    return Livewire::actingAs($test->candidate)
        ->test('company-review', ['application' => $application ?? $test->application]);
}

function validReview(array $overrides = []): array
{
    return array_merge([
        'overallRating' => '2',
        'communicationRating' => '1',
        'jobAsDescribed' => JobAsDescribed::No->value,
        'title' => 'Three weeks of silence after the interview',
        'body' => 'The first call was friendly, then nobody answered my two follow-up emails until the rejection.',
    ], $overrides);
}

function fillReview($component, array $values)
{
    foreach ($values as $field => $value) {
        $component->set($field, $value);
    }

    return $component;
}

test('the application page offers a review once the application qualifies', function () {
    $this->actingAs($this->candidate)
        ->get(route('candidate.applications.show', $this->application))
        ->assertOk()
        ->assertSee('Review this hiring process');
});

test('an application that does not qualify offers nothing', function () {
    $fresh = Application::factory()
        ->for(JobPosting::factory())
        ->create(['candidate_profile_id' => $this->candidate->candidateProfile->id]);

    $this->actingAs($this->candidate)
        ->get(route('candidate.applications.show', $fresh))
        ->assertOk()
        ->assertDontSee('Review this hiring process')
        ->assertDontSee('Your review of');
});

test('the component refuses anyone but the candidate behind the application', function () {
    Livewire::actingAs(candidateUser())
        ->test('company-review', ['application' => $this->application])
        ->assertForbidden();
});

test('a review is saved for staff to check, and staff are told', function () {
    $staff = staffWithTwoFactor();
    $staffFromTheCompany = staffWithTwoFactor();
    Membership::factory()->for($staffFromTheCompany)->for($this->company)->create();
    $owner = employerUser($this->company, MembershipRole::Owner);

    fillReview(reviewForm($this)->call('open'), validReview())
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false);

    $review = CompanyReview::sole();

    expect($review->company_id)->toBe($this->company->id)
        ->and($review->candidate_profile_id)->toBe($this->candidate->candidateProfile->id)
        ->and($review->application_id)->toBe($this->application->id)
        ->and($review->overall_rating)->toBe(2)
        ->and($review->communication_rating)->toBe(1)
        ->and($review->job_as_described)->toBe(JobAsDescribed::No)
        ->and($review->moderation_status)->toBe(ModerationStatus::Pending)
        ->and($review->published_at)->toBeNull();

    Notification::assertSentTo($staff, CompanyReviewAwaitingReview::class, function ($notification) use ($staff) {
        $mail = $notification->toMail($staff);

        return str_contains($mail->subject, 'Acme Hiring')
            && ! str_contains(implode(' ', $mail->introLines), $this->candidate->name);
    });
    Notification::assertNotSentTo([$staffFromTheCompany, $owner], CompanyReviewAwaitingReview::class);
});

test('the form holds a review to the length and ratings it promises', function (array $values, string $field) {
    fillReview(reviewForm($this)->call('open'), validReview($values))
        ->call('save')
        ->assertHasErrors($field);

    expect(CompanyReview::count())->toBe(0);
})->with([
    'no overall rating' => [['overallRating' => null], 'overallRating'],
    'rating above five' => [['overallRating' => '6'], 'overallRating'],
    'rating below one' => [['communicationRating' => '0'], 'communicationRating'],
    'unknown answer' => [['jobAsDescribed' => 'maybe'], 'jobAsDescribed'],
    'no headline' => [['title' => ''], 'title'],
    'headline too long' => [['title' => str_repeat('a', 101)], 'title'],
    'body too short' => [['body' => str_repeat('a', 49)], 'body'],
    'body too long' => [['body' => str_repeat('a', 2001)], 'body'],
    'body padded with spaces' => [['body' => 'a'.str_repeat(' ', 49)], 'body'],
    'headline of spaces only' => [['title' => '     '], 'title'],
]);

test('spaces around the text are not stored', function () {
    fillReview(reviewForm($this)->call('open'), validReview([
        'title' => '   Slow   but fair  ',
        'body' => "\n  ".validReview()['body']."   \n",
    ]))->call('save')->assertHasNoErrors();

    expect(CompanyReview::sole()->title)->toBe('Slow but fair')
        ->and(CompanyReview::sole()->body)->toBe(validReview()['body']);
});

test('deleting and rewriting cannot mail staff without end', function () {
    $staff = staffWithTwoFactor();

    foreach (range(1, SubmissionLimits::REVIEW_SAVES_PER_DAY) as $round) {
        fillReview(reviewForm($this)->call('open'), validReview())->call('save')->assertHasNoErrors();
        reviewForm($this)->call('delete');
    }

    fillReview(reviewForm($this)->call('open'), validReview())->call('save');

    expect(CompanyReview::count())->toBe(0);
    Notification::assertSentToTimes($staff, CompanyReviewAwaitingReview::class, SubmissionLimits::REVIEW_SAVES_PER_DAY);

    $this->travel(1)->day();
    fillReview(reviewForm($this)->call('open'), validReview())->call('save')->assertHasNoErrors();

    expect(CompanyReview::count())->toBe(1);
});

test('a refused save keeps the form open and the text as typed', function () {
    RateLimiter::increment(SubmissionLimits::reviewSaveKey($this->candidate), 86400, SubmissionLimits::REVIEW_SAVES_PER_DAY);

    fillReview(reviewForm($this)->call('open'), validReview())
        ->call('save')
        ->assertSet('showForm', true)
        ->assertSet('title', validReview()['title']);

    expect(CompanyReview::count())->toBe(0);
});

test('a candidate whose application does not qualify cannot open or save the form', function () {
    $fresh = Application::factory()
        ->for(JobPosting::factory())
        ->create(['candidate_profile_id' => $this->candidate->candidateProfile->id]);

    reviewForm($this, $fresh)->call('open')->assertForbidden();
    fillReview(reviewForm($this, $fresh), validReview())->call('save')->assertForbidden();

    expect(CompanyReview::count())->toBe(0);
});

test('editing a published review sends it back to staff', function () {
    $review = CompanyReview::factory()->published()->for($this->application)->create();
    $staff = staffWithTwoFactor();

    reviewForm($this)
        ->call('open')
        ->assertSet('title', $review->title)
        ->set('title', 'Updated after a second look')
        ->call('save')
        ->assertHasNoErrors();

    $review->refresh();

    expect($review->title)->toBe('Updated after a second look')
        ->and($review->moderation_status)->toBe(ModerationStatus::Pending)
        ->and($review->published_at)->toBeNull();

    Notification::assertSentTo($staff, CompanyReviewAwaitingReview::class);
});

test('a rejected review can be edited and goes back for a check', function () {
    $review = CompanyReview::factory()->for($this->application)->create(['moderation_status' => ModerationStatus::Rejected]);

    $this->actingAs($this->candidate)
        ->get(route('candidate.applications.show', $this->application))
        ->assertSee('Not published')
        ->assertSee('did not publish this review');

    fillReview(reviewForm($this)->call('open'), validReview())->call('save')->assertHasNoErrors();

    expect($review->fresh()->moderation_status)->toBe(ModerationStatus::Pending);
});

test('editing a review that is already waiting does not mail staff again', function () {
    CompanyReview::factory()->for($this->application)->create();
    $staff = staffWithTwoFactor();

    fillReview(reviewForm($this)->call('open'), validReview())->call('save')->assertHasNoErrors();

    Notification::assertNotSentTo($staff, CompanyReviewAwaitingReview::class);
});

test('one review per company shows on every application there, about the latest one that qualifies', function () {
    $review = CompanyReview::factory()->published()->for($this->application)->create();

    $this->travel(2)->months();
    $second = rejectedApplication($this, 'Platform Engineer');

    $this->actingAs($this->candidate)
        ->get(route('candidate.applications.show', $second))
        ->assertSee('Your review of Acme Hiring')
        ->assertSee('About your application for Backend Developer')
        ->assertDontSee('Write a review');

    fillReview(reviewForm($this, $second)->call('open'), validReview())->call('save')->assertHasNoErrors();

    expect(CompanyReview::count())->toBe(1)
        ->and($review->fresh()->application_id)->toBe($second->id);

    // Edited from the older application's page, it still covers the newer
    // process: that is the one the writer last went through.
    fillReview(reviewForm($this)->call('open'), validReview(['title' => 'Edited again']))->call('save');

    expect($review->fresh()->application_id)->toBe($second->id);
});

test('a later application that does not qualify does not move the review', function () {
    $review = CompanyReview::factory()->for($this->application)->create();

    $this->travel(1)->month();
    Application::factory()
        ->for(JobPosting::factory()->for($this->company))
        ->create(['candidate_profile_id' => $this->candidate->candidateProfile->id]);

    fillReview(reviewForm($this)->call('open'), validReview())->call('save');

    expect($review->fresh()->application_id)->toBe($this->application->id);
});

test('the writer can delete their review', function () {
    CompanyReview::factory()->published()->for($this->application)->create();

    reviewForm($this)->call('delete');

    expect(CompanyReview::count())->toBe(0);
});

test('a writer who has joined the company can delete but not edit', function () {
    CompanyReview::factory()->published()->for($this->application)->create();
    Membership::factory()->for($this->candidate)->for($this->company)->create();

    reviewForm($this)
        ->assertSee('You have since joined this company')
        ->assertDontSee('Edit')
        ->call('open')
        ->assertForbidden();
});

test('review text is shown as written, never as markup', function () {
    CompanyReview::factory()->for($this->application)->create([
        'body' => '<script>alert(1)</script> The process took far too long for a junior role, honestly.',
    ]);

    reviewForm($this)
        ->assertDontSeeHtml('<script>alert(1)</script>')
        ->assertSeeHtml('&lt;script&gt;alert(1)&lt;/script&gt;');
});

test('the writer is warned about contact details before staff look', function () {
    CompanyReview::factory()->for($this->application)->create([
        'body' => 'Email me at sakib@example.com or call +44 7700 900123 to hear the rest of the story.',
    ]);

    reviewForm($this)->assertSee('an email address and a phone number');
});

test('text flags catch emails, phone numbers and links, and leave ordinary numbers alone', function (string $text, array $flags) {
    expect(ReviewTextFlags::in($text))->toBe($flags);
})->with([
    'email' => ['Write to hr@acme.example.org for details.', [ReviewTextFlags::EMAIL]],
    'phone' => ['Call them on 01712-345678 before noon.', [ReviewTextFlags::PHONE]],
    'international phone' => ['Ring +44 (20) 7946 0958.', [ReviewTextFlags::PHONE]],
    'web address' => ['See https://example.com/story for more.', [ReviewTextFlags::LINK]],
    'www' => ['My blog at WWW.example.net has it all.', [ReviewTextFlags::LINK]],
    'bare domain' => ['Full write-up on mysite.io soon.', [ReviewTextFlags::LINK]],
    'salary' => ['They offered 50,000 a year, not 65,000.', []],
    'year range' => ['I was there 2019-2023 before applying.', []],
    'technology name' => ['They asked about ASP.NET and Node.js.', []],
    'everything' => ['me@x.com, 0171 234 5678 9, www.x.com', [ReviewTextFlags::EMAIL, ReviewTextFlags::PHONE, ReviewTextFlags::LINK]],
]);

test('an interview the candidate withdrew from still qualifies through the form', function () {
    $interviewed = Application::factory()
        ->for(JobPosting::factory()->for(Company::factory()))
        ->create(['candidate_profile_id' => $this->candidate->candidateProfile->id, 'stage' => ApplicationStage::Interview]);

    fillReview(reviewForm($this, $interviewed)->call('open'), validReview())->call('save')->assertHasNoErrors();

    expect(CompanyReview::sole()->application_id)->toBe($interviewed->id);
});
