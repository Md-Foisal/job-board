<?php

use App\Actions\ApproveJobPosting;
use App\Actions\RejectJobPosting;
use App\Actions\SaveJobPosting;
use App\Enums\AccountStatus;
use App\Enums\EmploymentType;
use App\Enums\MembershipRole;
use App\Enums\ModerationAction;
use App\Enums\ModerationStatus;
use App\Enums\ReportStatus;
use App\Enums\StaffRole;
use App\Enums\WorkplaceType;
use App\Filament\Resources\JobPostings\Pages\ManageJobPostings;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Membership;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

function queueModerator(): User
{
    return User::factory()->withTwoFactor()->create(['staff_role' => StaffRole::Moderator]);
}

function publishablePosting(array $overrides = []): array
{
    return array_merge([
        'title' => 'Laravel Developer',
        'description' => '<p>Build things.</p>',
        'employment_type' => EmploymentType::FullTime,
        'workplace_type' => WorkplaceType::Remote,
        'location_country' => 'BD',
        'expires_at' => now()->addMonth(),
        'publish' => true,
    ], $overrides);
}

it('shows staff the postings waiting for review, but never an unfinished draft', function () {
    JobPosting::factory()->pendingModeration()->create(['title' => 'Waiting Role']);
    JobPosting::factory()->draft()->pendingModeration()->create(['title' => 'Unfinished Draft']);

    $this->actingAs(queueModerator())
        ->get('/admin/moderation/jobs')
        ->assertOk()
        ->assertSee('Waiting Role')
        ->assertDontSee('Unfinished Draft');
});

it('keeps the queue closed to anyone who is not staff', function () {
    $this->actingAs(candidateUser())
        ->get('/admin/moderation/jobs')
        ->assertForbidden();
});

it('approves a posting, records who did it, and closes its reports as reviewed', function () {
    $staff = queueModerator();
    $posting = JobPosting::factory()->pendingModeration()->create();
    $report = $posting->reports()->create(['reporter_id' => User::factory()->create()->id, 'reason' => 'Looks off']);

    $event = app(ApproveJobPosting::class)($posting, $staff);

    expect($posting->fresh()->moderation_status)->toBe(ModerationStatus::Approved)
        ->and($event->action)->toBe(ModerationAction::ApproveJobPosting)
        ->and($event->admin_id)->toBe($staff->id)
        ->and($report->fresh()->review_status)->toBe(ReportStatus::Reviewed);
});

it('rejects a posting with its reason, and closes its reports as actioned', function () {
    $posting = JobPosting::factory()->pendingModeration()->create();
    $report = $posting->reports()->create(['reporter_id' => User::factory()->create()->id, 'reason' => 'Asks for a fee']);

    $event = app(RejectJobPosting::class)($posting, queueModerator(), 'Charges applicants a fee.');

    expect($posting->fresh()->moderation_status)->toBe(ModerationStatus::Rejected)
        ->and($event->reason)->toBe('Charges applicants a fee.')
        ->and($report->fresh()->review_status)->toBe(ReportStatus::Actioned);
});

it('will not reject without a reason', function () {
    $posting = JobPosting::factory()->pendingModeration()->create();
    $this->actingAs(queueModerator());

    Livewire::test(ManageJobPostings::class)
        ->callAction(TestAction::make('reject')->table($posting), data: ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);

    expect($posting->fresh()->moderation_status)->toBe(ModerationStatus::Pending);
});

it('hides both decisions from staff on their own employer\'s posting', function () {
    $company = Company::factory()->create();
    $posting = JobPosting::factory()->for($company)->pendingModeration()->create();

    $staff = queueModerator();
    Membership::factory()->for($staff)->for($company)->create(['role' => MembershipRole::Member]);
    $this->actingAs($staff->fresh());

    Livewire::test(ManageJobPostings::class)
        ->assertActionHidden(TestAction::make('approve')->table($posting))
        ->assertActionHidden(TestAction::make('reject')->table($posting));
});

it('holds a new employer\'s posting for review', function () {
    $company = Company::factory()->create();
    $owner = employerUser($company, MembershipRole::Owner);

    $posting = app(SaveJobPosting::class)($company, $owner, publishablePosting());

    expect($posting->moderation_status)->toBe(ModerationStatus::Pending);
});

it('lets a company with a clean record publish straight through', function () {
    $company = Company::factory()->create();
    $owner = employerUser($company, MembershipRole::Owner);
    $staff = queueModerator();

    foreach (range(1, Company::TRUSTED_AFTER_APPROVALS) as $n) {
        $earlier = app(SaveJobPosting::class)($company, $owner, publishablePosting(['title' => "Role {$n}"]));
        app(ApproveJobPosting::class)($earlier, $staff);
    }

    $next = app(SaveJobPosting::class)($company, $owner, publishablePosting(['title' => 'Next Role']));

    expect($next->moderation_status)->toBe(ModerationStatus::Approved);
});

it('never lets a company with a rejection on record skip the queue', function () {
    $company = Company::factory()->create();
    $owner = employerUser($company, MembershipRole::Owner);
    $staff = queueModerator();

    foreach (range(1, Company::TRUSTED_AFTER_APPROVALS) as $n) {
        $earlier = app(SaveJobPosting::class)($company, $owner, publishablePosting(['title' => "Role {$n}"]));
        app(ApproveJobPosting::class)($earlier, $staff);
    }

    $bad = app(SaveJobPosting::class)($company, $owner, publishablePosting(['title' => 'Bad Role']));
    app(RejectJobPosting::class)($bad, $staff, 'Misleading pay.');
    app(ApproveJobPosting::class)($bad, $staff);

    $next = app(SaveJobPosting::class)($company, $owner, publishablePosting(['title' => 'Next Role']));

    expect($next->moderation_status)->toBe(ModerationStatus::Pending);
});

it('sends an approved posting back for review when it is edited', function () {
    $company = Company::factory()->create();
    $owner = employerUser($company, MembershipRole::Owner);

    $posting = app(SaveJobPosting::class)($company, $owner, publishablePosting());
    app(ApproveJobPosting::class)($posting, queueModerator());

    $edited = app(SaveJobPosting::class)($company, $owner, publishablePosting(['title' => 'Something else entirely']), $posting->fresh());

    expect($edited->moderation_status)->toBe(ModerationStatus::Pending);
});

it('never offers to approve a banned company\'s posting, and refuses if asked anyway', function () {
    $company = Company::factory()->create();
    $company->account_status = AccountStatus::Suspended;
    $company->save();
    $posting = JobPosting::factory()->for($company)->pendingModeration()->create();

    $this->actingAs(queueModerator());

    Livewire::test(ManageJobPostings::class)
        ->assertActionHidden(TestAction::make('approve')->table($posting));

    expect(fn () => app(ApproveJobPosting::class)($posting, queueModerator()))
        ->toThrow(DomainException::class);
});

it('keeps the employer record the reviewer sees in step with the trust tier', function () {
    $company = Company::factory()->create();
    $staff = queueModerator();

    $approved = JobPosting::factory()->for($company)->pendingModeration()->create();
    $rejected = JobPosting::factory()->for($company)->pendingModeration()->create();
    app(ApproveJobPosting::class)($approved, $staff);
    app(RejectJobPosting::class)($rejected, $staff, 'Misleading pay.');

    expect($company->postingModerationRecord())->toBe(['approved' => 1, 'rejected' => 1])
        ->and($company->isTrustedPoster())->toBeFalse();
});
