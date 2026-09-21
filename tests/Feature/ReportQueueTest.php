<?php

use App\Enums\MembershipRole;
use App\Enums\ModerationAction;
use App\Enums\ModerationStatus;
use App\Enums\ReportStatus;
use App\Filament\Resources\Reports\Pages\ManageReports;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Membership;
use App\Models\ModerationEvent;
use App\Models\Report;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

function reportAgainst(JobPosting|Company $subject, string $reason = 'Something is wrong here'): Report
{
    return $subject->reports()->create([
        'reporter_id' => User::factory()->create()->id,
        'reason' => $reason,
    ]);
}

it('shows one row per reported thing, however many reports it has', function () {
    $posting = JobPosting::factory()->create(['title' => 'Much Reported Role']);
    $company = Company::factory()->create(['name' => 'Once Reported Ltd']);

    reportAgainst($posting);
    reportAgainst($posting);
    reportAgainst($posting);
    reportAgainst($company);

    $this->actingAs(staffWithTwoFactor());

    Livewire::test(ManageReports::class)
        ->assertCountTableRecords(2)
        ->assertSee('Much Reported Role')
        ->assertSee('Once Reported Ltd');
});

it('keeps the reports queue closed to anyone who is not staff', function () {
    $this->actingAs(candidateUser())
        ->get('/admin/moderation/reports')
        ->assertForbidden();
});

it('dismisses every open report on a subject with one recorded decision, and leaves the subject alone', function () {
    $posting = JobPosting::factory()->create();
    reportAgainst($posting);
    $latest = reportAgainst($posting);
    $this->actingAs(staffWithTwoFactor());

    Livewire::test(ManageReports::class)
        ->callAction(TestAction::make('dismiss')->table($latest), data: ['note' => 'Checked the company; the pay is real.']);

    expect($posting->reports()->where('review_status', ReportStatus::Pending->value)->count())->toBe(0)
        ->and($posting->fresh()->moderation_status)->toBe(ModerationStatus::Approved)
        ->and(ModerationEvent::where('action', ModerationAction::DismissReports)->count())->toBe(1);
});

it('takes a reported posting down through the same decision the posting queue uses', function () {
    $posting = JobPosting::factory()->create();
    $latest = reportAgainst($posting, 'Asks applicants to pay a registration fee');
    $this->actingAs(staffWithTwoFactor());

    Livewire::test(ManageReports::class)
        ->callAction(TestAction::make('rejectPosting')->table($latest), data: ['reason' => 'Charges applicants a fee.']);

    expect($posting->fresh()->moderation_status)->toBe(ModerationStatus::Rejected)
        ->and($latest->fresh()->review_status)->toBe(ReportStatus::Actioned);
});

it('hides the decisions from staff when the report reaches their own employer', function () {
    $company = Company::factory()->create();
    $posting = JobPosting::factory()->for($company)->create();
    $latest = reportAgainst($posting);

    $staff = staffWithTwoFactor();
    Membership::factory()->for($staff)->for($company)->create(['role' => MembershipRole::Member]);
    $this->actingAs($staff->fresh());

    Livewire::test(ManageReports::class)
        ->assertActionHidden(TestAction::make('dismiss')->table($latest))
        ->assertActionHidden(TestAction::make('rejectPosting')->table($latest));
});

it('moves a subject from open to closed once it has been dealt with', function () {
    $posting = JobPosting::factory()->create();
    $latest = reportAgainst($posting);
    $this->actingAs(staffWithTwoFactor());

    Livewire::test(ManageReports::class)
        ->callAction(TestAction::make('dismiss')->table($latest))
        ->assertCountTableRecords(0);

    Livewire::test(ManageReports::class)
        ->set('activeTab', 'closed')
        ->assertCountTableRecords(1);
});
