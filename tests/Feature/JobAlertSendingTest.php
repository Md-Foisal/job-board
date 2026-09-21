<?php

use App\Enums\AccountStatus;
use App\Models\JobAlert;
use App\Models\JobPosting;
use App\Notifications\JobAlertMatches;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

function alertFor(array $criteria = ['q' => 'Laravel'], array $attributes = []): JobAlert
{
    return JobAlert::factory()->create(array_merge([
        'user_id' => candidateUser()->id,
        'criteria' => $criteria,
        'created_at' => now()->subDays(10),
    ], $attributes));
}

function postingPublished(string $title, $at = null): JobPosting
{
    return JobPosting::factory()->create([
        'title' => $title,
        'published_at' => $at ?? now()->subHour(),
        'expires_at' => now()->addMonth(),
    ]);
}

test('an alert emails the new postings that match, and only those', function () {
    Notification::fake();
    $jobAlert = alertFor();
    $match = postingPublished('Laravel Developer');
    postingPublished('Accountant');
    postingPublished('Laravel Lead', now()->subDays(20));

    $this->artisan('job-alerts:send')->assertSuccessful();

    Notification::assertSentTo($jobAlert->user, JobAlertMatches::class,
        fn (JobAlertMatches $notification) => $notification->total === 1
            && $notification->jobPostings->modelKeys() === [$match->id]);

    expect($jobAlert->fresh()->last_sent_at)->not->toBeNull();
});

test('a posting is never emailed twice by the same alert, even one approved late', function () {
    Notification::fake();
    $jobAlert = alertFor();
    postingPublished('Laravel Developer');

    $this->artisan('job-alerts:send');
    Notification::assertSentTimes(JobAlertMatches::class, 1);

    // Submitted before the last run but approved after it: a time window
    // would miss this one; remembering what was sent does not.
    $lateApproval = JobPosting::factory()->pendingModeration()->create([
        'title' => 'Laravel Engineer',
        'published_at' => now()->subHours(2),
        'expires_at' => now()->addMonth(),
    ]);

    $this->travel(1)->day();
    $lateApproval->forceFill(['moderation_status' => App\Enums\ModerationStatus::Approved])->save();
    $this->artisan('job-alerts:send');

    Notification::assertSentTimes(JobAlertMatches::class, 2);
    Notification::assertSentTo($jobAlert->user, JobAlertMatches::class,
        fn (JobAlertMatches $notification) => $notification->jobPostings->modelKeys() === [$lateApproval->id]);
});

test('nothing is sent when nothing new matches, and the alert still waits its turn', function () {
    Notification::fake();
    $jobAlert = alertFor(attributes: ['frequency' => 'weekly']);

    $this->artisan('job-alerts:send');
    Notification::assertNothingSent();

    postingPublished('Laravel Developer');
    $this->travel(3)->days();
    $this->artisan('job-alerts:send');
    Notification::assertNothingSent();

    $this->travel(4)->days();
    $this->artisan('job-alerts:send');
    Notification::assertSentTo($jobAlert->user, JobAlertMatches::class);
});

test('a daily alert runs once a day however late the previous run finished', function () {
    $jobAlert = alertFor(attributes: ['last_sent_at' => now()->subDay()->addSeconds(5)]);

    expect(JobAlert::due()->whereKey($jobAlert->id)->exists())->toBeTrue();

    // Not fillable on purpose: only a run sets it.
    $jobAlert->forceFill(['last_sent_at' => now()->subHours(2)])->save();

    expect(JobAlert::due()->whereKey($jobAlert->id)->exists())->toBeFalse();
});

test('paused alerts and suspended or deleted accounts get nothing', function () {
    Notification::fake();
    postingPublished('Laravel Developer');

    $paused = alertFor(attributes: ['is_active' => false]);
    $suspended = alertFor();
    $suspended->user->forceFill(['account_status' => AccountStatus::Suspended])->save();
    $deleted = alertFor();
    $deleted->user->delete();

    $this->artisan('job-alerts:send');

    Notification::assertNothingSent();
});

test('the email lists ten and links to the rest, with one-click unsubscribe headers', function () {
    $jobAlert = alertFor(['q' => 'Laravel'], ['name' => 'Laravel jobs']);
    foreach (range(1, 12) as $n) {
        postingPublished("Laravel Role {$n}");
    }

    $matches = JobPosting::query()->active()->latest('published_at')->get();
    $mail = (new JobAlertMatches($jobAlert, $matches->take(10), 12))->toMail($jobAlert->user);

    expect($mail->subject)->toBe('12 new jobs for "Laravel jobs"');

    $rendered = (string) $mail->render();
    expect($rendered)->toContain('See all 12')
        ->and($rendered)->toContain('Unsubscribe from this alert');

    $message = new Symfony\Component\Mime\Email;
    foreach ($mail->callbacks as $callback) {
        $callback($message);
    }

    expect($message->getHeaders()->get('List-Unsubscribe-Post')->getBodyAsString())->toBe('List-Unsubscribe=One-Click')
        ->and($message->getHeaders()->get('List-Unsubscribe')->getBodyAsString())->toContain('/job-alerts/'.$jobAlert->id.'/unsubscribe?signature=');
});

test('opening the unsubscribe link only asks; confirming switches the alert off', function () {
    $jobAlert = alertFor();
    $url = URL::signedRoute('job-alerts.unsubscribe', ['jobAlert' => $jobAlert->id]);

    $this->get($url)->assertOk()->assertSee('Stop this job alert?');
    expect($jobAlert->fresh()->is_active)->toBeTrue();

    $this->post($url)->assertOk()->assertSee("You're unsubscribed");
    expect($jobAlert->fresh()->is_active)->toBeFalse();
});

test('the one-click POST is let through without a CSRF token', function () {
    // Tests skip CSRF checks altogether, so a request cannot prove this;
    // the exemption itself is what a mail client's POST depends on.
    $request = Illuminate\Http\Request::create('/job-alerts/1/unsubscribe', 'POST');
    $middleware = app(Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);

    expect(collect($middleware->getExcludedPaths())->contains(fn ($path) => $request->is($path)))->toBeTrue();
});

test('an unsigned or tampered unsubscribe link is refused', function () {
    $jobAlert = alertFor();
    $other = alertFor();
    $url = URL::signedRoute('job-alerts.unsubscribe', ['jobAlert' => $jobAlert->id]);

    $this->post("/job-alerts/{$jobAlert->id}/unsubscribe")->assertForbidden();
    $this->post(str_replace("/{$jobAlert->id}/", "/{$other->id}/", $url))->assertForbidden();

    expect($jobAlert->fresh()->is_active)->toBeTrue()
        ->and($other->fresh()->is_active)->toBeTrue();
});

test('unsubscribing from an alert that was deleted still says so plainly', function () {
    $jobAlert = alertFor();
    $url = URL::signedRoute('job-alerts.unsubscribe', ['jobAlert' => $jobAlert->id]);
    $jobAlert->delete();

    $this->get($url)->assertOk()->assertSee('no longer exists');
    $this->post($url)->assertOk();
});

test('alerts go out every morning, Dhaka time', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'job-alerts:send'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 8 * * *')
        ->and($event->timezone)->toBe('Asia/Dhaka');
});
