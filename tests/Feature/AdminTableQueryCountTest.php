<?php

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\StaffRole;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * How many queries one page of an admin table costs. The number itself
 * does not matter; it must not grow with the number of rows.
 *
 * The page is rendered once before counting. The panel builds its
 * navigation (and the badge counts in it) once per application, and a
 * test keeps one application across its requests, so without the warm-up
 * the first measurement would carry those queries and the second would not.
 */
function queriesToRender(string $url, User $staff): int
{
    test()->actingAs($staff)->get($url)->assertOk();

    DB::flushQueryLog();
    DB::enableQueryLog();

    test()->get($url)->assertOk();

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

function reportedPostings(int $howMany): void
{
    JobPosting::factory()->count($howMany)->create()->each(fn (JobPosting $posting) => $posting->reports()->create([
        'reporter_id' => User::factory()->create()->id,
        'reason' => 'Something is wrong here',
    ]));
}

function reportedCompanies(int $howMany): void
{
    Company::factory()->count($howMany)->create()->each(function (Company $company) {
        Membership::factory()->for($company)->create(['role' => MembershipRole::Owner]);
        $company->reports()->create([
            'reporter_id' => User::factory()->create()->id,
            'reason' => 'Something is wrong here',
        ]);
    });
}

it('renders the reports queue with the same number of queries however many rows it shows', function () {
    $staff = staffWithTwoFactor(StaffRole::SuperAdmin);

    reportedPostings(2);
    reportedCompanies(1);
    $few = queriesToRender('/admin/moderation/reports', $staff);

    reportedPostings(4);
    reportedCompanies(3);
    $many = queriesToRender('/admin/moderation/reports', $staff);

    expect($many)->toBe($few);
});

it('renders the companies list with the same number of queries however many rows it shows', function () {
    $staff = staffWithTwoFactor(StaffRole::SuperAdmin);

    reportedCompanies(2);
    $few = queriesToRender('/admin/companies', $staff);

    reportedCompanies(6);
    $many = queriesToRender('/admin/companies', $staff);

    expect($many)->toBe($few);
});

it('renders the job moderation queue with the same number of queries however many rows it shows', function () {
    $staff = staffWithTwoFactor(StaffRole::SuperAdmin);

    JobPosting::factory()->count(2)->pendingModeration()->create();
    $few = queriesToRender('/admin/moderation/jobs', $staff);

    JobPosting::factory()->count(6)->pendingModeration()->create();
    $many = queriesToRender('/admin/moderation/jobs', $staff);

    expect($many)->toBe($few);
});

it('answers worksAt from loaded active memberships the same way the database does', function () {
    $staff = staffWithTwoFactor();
    $current = Company::factory()->create();
    $former = Company::factory()->create();
    $unrelated = Company::factory()->create();
    Membership::factory()->for($staff)->for($current)->create(['role' => MembershipRole::Member]);
    Membership::factory()->for($staff)->for($former)->create([
        'role' => MembershipRole::Member,
        'status' => MembershipStatus::Inactive,
    ]);

    $fromDatabase = $staff->fresh();
    $fromMemory = $staff->fresh()->load('activeMemberships');

    foreach ([$current, $former, $unrelated] as $company) {
        expect($fromMemory->worksAt($company))->toBe($fromDatabase->worksAt($company));
    }

    expect($fromMemory->worksAt($current))->toBeTrue()
        ->and($fromMemory->worksAt($former))->toBeFalse();
});

it('does not trust a filtered memberships list for worksAt', function () {
    $staff = staffWithTwoFactor();
    $company = Company::factory()->create();
    Membership::factory()->for($staff)->for($company)->create(['role' => MembershipRole::Member]);

    $filtered = $staff->fresh()->load(['memberships' => fn ($query) => $query->whereRaw('1 = 0')]);

    expect($filtered->worksAt($company))->toBeTrue();
});

it('stops recusing staff on the next request once their membership has ended', function () {
    $company = Company::factory()->create(['name' => 'Former Employer Ltd']);
    $posting = JobPosting::factory()->for($company)->create();
    $posting->reports()->create([
        'reporter_id' => User::factory()->create()->id,
        'reason' => 'Something is wrong here',
    ]);

    $staff = staffWithTwoFactor();
    $membership = Membership::factory()->for($staff)->for($company)->create(['role' => MembershipRole::Member]);

    $this->actingAs($staff)->get('/admin/moderation/reports')->assertOk()->assertDontSee('Dismiss');

    $membership->update(['status' => MembershipStatus::Inactive]);

    $this->actingAs($staff)->get('/admin/moderation/reports')->assertOk()->assertSee('Dismiss');
});
