<?php

use App\Actions\RecordJobView;
use App\Enums\MembershipRole;
use App\Models\JobPosting;
use App\Models\JobPostingDailyStat;
use App\Models\JobView;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;

const BROWSER_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36';

beforeEach(function () {
    $this->withHeader('User-Agent', BROWSER_USER_AGENT);
});

function viewsOn(JobPosting $job): array
{
    return JobPostingDailyStat::query()
        ->where('job_posting_id', $job->id)
        ->orderBy('date')
        ->get()
        ->mapWithKeys(fn (JobPostingDailyStat $stat) => [$stat->date->toDateString() => $stat->views])
        ->all();
}

test('a guest opening a job counts as one view for today', function () {
    $job = JobPosting::factory()->create();

    $this->get(route('jobs.show', $job))->assertOk();

    expect(viewsOn($job))->toBe([today()->toDateString() => 1]);
});

test('the same session opening the same job again that day is not counted again', function () {
    $job = JobPosting::factory()->create();

    $this->get(route('jobs.show', $job))->assertOk();
    $this->get(route('jobs.show', $job))->assertOk();

    expect(viewsOn($job))->toBe([today()->toDateString() => 1]);
});

test('different sessions each count, into the same row for the day', function () {
    $job = JobPosting::factory()->create();

    $this->get(route('jobs.show', $job))->assertOk();
    $this->flushSession();
    $this->get(route('jobs.show', $job))->assertOk();

    expect(viewsOn($job))->toBe([today()->toDateString() => 2])
        ->and(JobPostingDailyStat::count())->toBe(1);
});

test('one session counts each job it opens, once each', function () {
    $first = JobPosting::factory()->create();
    $second = JobPosting::factory()->create();

    $this->get(route('jobs.show', $first))->assertOk();
    $this->get(route('jobs.show', $second))->assertOk();
    $this->get(route('jobs.show', $first))->assertOk();

    expect(viewsOn($first))->toBe([today()->toDateString() => 1])
        ->and(viewsOn($second))->toBe([today()->toDateString() => 1]);
});

test('the same session is counted again on the next day, in a new row', function () {
    $this->travelTo(now()->setTime(23, 50));
    $job = JobPosting::factory()->create(['expires_at' => now()->addMonth()]);
    $today = today()->toDateString();

    $this->get(route('jobs.show', $job))->assertOk();

    $this->travel(20)->minutes();
    $this->get(route('jobs.show', $job))->assertOk();

    expect(viewsOn($job))->toBe([
        $today => 1,
        today()->toDateString() => 1,
    ]);
});

test('crawlers are not counted', function (string $userAgent) {
    $job = JobPosting::factory()->create();

    $this->withHeader('User-Agent', $userAgent)->get(route('jobs.show', $job))->assertOk();

    expect(viewsOn($job))->toBe([]);
})->with([
    'Googlebot' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    'Bingbot' => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
    'a link preview' => 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
    'a script' => 'curl/8.5.0',
]);

test('a HEAD request is not counted', function () {
    $job = JobPosting::factory()->create();

    $this->call('HEAD', route('jobs.show', $job), server: ['HTTP_USER_AGENT' => BROWSER_USER_AGENT])->assertOk();

    expect(viewsOn($job))->toBe([]);
});

test('a request with no user agent is not counted', function () {
    $job = JobPosting::factory()->create();

    $this->withHeader('User-Agent', '')->get(route('jobs.show', $job))->assertOk();

    expect(viewsOn($job))->toBe([]);
});

test('the company\'s own people are not counted, whatever their role', function (MembershipRole $role) {
    $job = JobPosting::factory()->create();

    $this->actingAs(employerUser($job->company, $role))
        ->get(route('jobs.show', $job))
        ->assertOk();

    expect(viewsOn($job))->toBe([]);
})->with([MembershipRole::Owner, MembershipRole::Manager, MembershipRole::Member]);

test('a member previewing a draft is not counted', function () {
    $job = JobPosting::factory()->draft()->create();

    $this->actingAs(employerUser($job->company))
        ->get(route('jobs.show', $job))
        ->assertOk();

    expect(viewsOn($job))->toBe([]);
});

test('someone from another company is counted', function () {
    $job = JobPosting::factory()->create();

    $this->actingAs(employerUser())->get(route('jobs.show', $job))->assertOk();

    expect(viewsOn($job))->toBe([today()->toDateString() => 1]);
});

test('platform staff are not counted', function () {
    $job = JobPosting::factory()->create();

    $this->actingAs(staffUser())->get(route('jobs.show', $job))->assertOk();

    expect(viewsOn($job))->toBe([]);
});

test('a job the public cannot see records nothing', function () {
    $job = JobPosting::factory()->pendingModeration()->create();

    $this->get(route('jobs.show', $job))->assertNotFound();

    expect(viewsOn($job))->toBe([]);
});

test('the count itself refuses a posting the public cannot see, not only the page in front of it', function () {
    $visible = JobPosting::factory()->create();
    $draft = JobPosting::factory()->draft()->create();

    $request = Request::create('/', server: ['HTTP_USER_AGENT' => BROWSER_USER_AGENT]);
    $request->setLaravelSession(app('session')->driver());

    app(RecordJobView::class)($visible, $request);
    app(RecordJobView::class)($draft, $request);
    app(DeferredCallbackCollection::class)->invoke();

    expect(viewsOn($visible))->toBe([today()->toDateString() => 1])
        ->and(viewsOn($draft))->toBe([]);
});

test('a candidate is counted and the job joins their recently viewed list', function () {
    $job = JobPosting::factory()->create();
    $candidate = candidateUser();

    $this->actingAs($candidate)->get(route('jobs.show', $job))->assertOk();

    expect(viewsOn($job))->toBe([today()->toDateString() => 1])
        ->and(JobView::where('user_id', $candidate->id)->where('job_posting_id', $job->id)->count())->toBe(1);
});

test('a candidate opening a job again keeps one recently viewed row and moves its time', function () {
    $job = JobPosting::factory()->create();
    $candidate = candidateUser();

    $this->actingAs($candidate)->get(route('jobs.show', $job))->assertOk();
    $this->travel(2)->hours();
    $this->actingAs($candidate)->get(route('jobs.show', $job))->assertOk();

    $views = JobView::where('user_id', $candidate->id)->get();

    expect($views)->toHaveCount(1)
        ->and($views->first()->viewed_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

test('a guest leaves no recently viewed row', function () {
    $job = JobPosting::factory()->create();

    $this->get(route('jobs.show', $job))->assertOk();

    expect(JobView::count())->toBe(0);
});

test('a failed count is reported and the visitor still gets the page', function () {
    Exceptions::fake();
    $job = JobPosting::factory()->create();
    Schema::drop('job_posting_daily_stats');

    $this->get(route('jobs.show', $job))->assertOk()->assertSee($job->title);

    Exceptions::assertReported(QueryException::class);
});
