<?php

use App\Enums\AvailabilityStatus;
use App\Enums\MembershipRole;
use App\Filament\Resources\JobPostings\JobPostingResource;
use App\Filament\Resources\JobPostings\Pages\ManageJobPostings;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Membership;
use Livewire\Livewire;

test('the waiting queue holds only postings still open; closed and lapsed ones wait on their own tab', function () {
    $open = JobPosting::factory()->pendingModeration()->create(['title' => 'Open And Waiting']);
    $closed = JobPosting::factory()->pendingModeration()->create([
        'title' => 'Closed While Waiting',
        'availability_status' => AvailabilityStatus::Closed,
    ]);
    $lapsed = JobPosting::factory()->pendingModeration()->create([
        'title' => 'Lapsed While Waiting',
        'expires_at' => now()->subDay(),
    ]);

    $this->actingAs(staffWithTwoFactor());

    Livewire::test(ManageJobPostings::class)
        ->assertCanSeeTableRecords([$open])
        ->assertCanNotSeeTableRecords([$closed, $lapsed])
        ->set('activeTab', 'not_open')
        ->assertCanSeeTableRecords([$closed, $lapsed])
        ->assertCanNotSeeTableRecords([$open]);

    expect(JobPostingResource::getNavigationBadge())->toBe('1');
});

test('a reopened posting is back in the queue without anyone moving it', function () {
    $posting = JobPosting::factory()->pendingModeration()->create(['availability_status' => AvailabilityStatus::Closed]);

    expect(JobPosting::awaitingReview()->whereKey($posting->id)->exists())->toBeFalse();

    // Not fillable on purpose (SaveJobPosting); the employer's Reopen
    // sets it the same way.
    $posting->forceFill(['availability_status' => AvailabilityStatus::Active])->save();

    expect(JobPosting::awaitingReview()->whereKey($posting->id)->exists())->toBeTrue();
});

test('the recruiter profile keeps the workspace it was opened from', function () {
    $first = Company::factory()->create(['name' => 'First Workspace Ltd']);
    $second = Company::factory()->create(['name' => 'Second Workspace Ltd']);
    $user = employerUser($first, MembershipRole::Member);
    Membership::factory()->for($user)->for($second)->create(['role' => MembershipRole::Member]);

    $this->actingAs($user)
        ->get(route('employer.recruiter-profile.edit', ['company' => $second->slug]))
        ->assertOk()
        // The workspace's own job list link is only in its sidebar; the
        // switcher lists every company's dashboard, so that would not tell.
        ->assertSee(route('employer.jobs.index', $second))
        ->assertDontSee(route('employer.jobs.index', $first));

    // Someone else's company in the address is ignored, not trusted.
    $stranger = Company::factory()->create(['name' => 'Not Theirs Ltd']);
    $this->actingAs($user)
        ->get(route('employer.recruiter-profile.edit', ['company' => $stranger->slug]))
        ->assertOk()
        ->assertDontSee(route('employer.jobs.index', $stranger));
});

test('each empty tab says what it is empty of', function () {
    $this->actingAs(staffWithTwoFactor());

    Livewire::test(ManageJobPostings::class)
        ->assertSee('Nothing is waiting')
        ->set('activeTab', 'approved')
        ->assertSee('Nothing approved yet')
        ->assertDontSee('Nothing is waiting')
        ->set('activeTab', 'not_open')
        ->assertSee('No closed or lapsed posting is waiting');
});
