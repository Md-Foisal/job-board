<?php

use App\Actions\ApproveJobPosting;
use App\Actions\DismissReports;
use App\Actions\RejectJobPosting;
use App\Actions\RequestCompanyDocuments;
use App\Actions\VerifyCompany;
use App\Enums\AvailabilityStatus;
use App\Enums\MembershipRole;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\User;
use App\Notifications\CompanyDocumentsRequested;
use App\Notifications\JobPostingApproved;
use App\Notifications\JobPostingRejected;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(fn () => Notification::fake());

test('an approval reaches the owner and managers, not every member', function () {
    $company = Company::factory()->create();
    $owner = employerUser($company, MembershipRole::Owner);
    $manager = employerUser($company, MembershipRole::Manager);
    $member = employerUser($company, MembershipRole::Member);
    $posting = JobPosting::factory()->for($company)->pendingModeration()->create();

    app(ApproveJobPosting::class)($posting, staffWithTwoFactor());

    Notification::assertSentTo([$owner, $manager], JobPostingApproved::class);
    Notification::assertNotSentTo($member, JobPostingApproved::class);
});

test('a rejection tells the employer what to fix', function () {
    $company = Company::factory()->create();
    $owner = employerUser($company, MembershipRole::Owner);
    $posting = JobPosting::factory()->for($company)->pendingModeration()->create();

    app(RejectJobPosting::class)($posting, staffWithTwoFactor(), 'Salary range is missing.');

    Notification::assertSentTo($owner, JobPostingRejected::class, function ($notification) use ($owner) {
        return str_contains(implode(' ', $notification->toMail($owner)->introLines), 'Salary range is missing.');
    });
});

test('a documents request reaches the people who can answer it', function () {
    $company = Company::factory()->create();
    $owner = employerUser($company, MembershipRole::Owner);

    app(RequestCompanyDocuments::class)($company, staffWithTwoFactor(), 'A trade licence, please.');

    Notification::assertSentTo($owner, CompanyDocumentsRequested::class);
});

test('the listing says a submitted posting is in review, but not a draft', function () {
    $company = Company::factory()->create();
    JobPosting::factory()->for($company)->pendingModeration()->create(['title' => 'Submitted Role']);
    JobPosting::factory()->for($company)->draft()->pendingModeration()->create(['title' => 'Draft Role']);

    Livewire::actingAs(employerUser($company, MembershipRole::Owner))
        ->test('pages::employer.job-listings', ['company' => $company])
        ->assertSeeInOrder(['Submitted Role', 'In review', 'Draft Role'])
        ->set('filter', AvailabilityStatus::Draft->value)
        ->assertDontSee('In review');
});

test('a sent-back posting shows the reviewer\'s reason, not a later report note', function () {
    $company = Company::factory()->create();
    $posting = JobPosting::factory()->for($company)->pendingModeration()->create();
    $staff = staffWithTwoFactor();

    app(RejectJobPosting::class)($posting, $staff, 'Salary range is missing.');

    $report = $posting->reports()->create(['reporter_id' => User::factory()->create()->id, 'reason' => 'Spam?']);
    app(DismissReports::class)($report, $staff, 'Not spam.');

    Livewire::actingAs(employerUser($company, MembershipRole::Owner))
        ->test('pages::employer.job-listings', ['company' => $company])
        ->assertSee('Needs changes')
        ->assertSee('Salary range is missing.')
        ->assertDontSee('Not spam.');
});

test('publishing says it went for review until the company is trusted', function () {
    $company = Company::factory()->create();
    $owner = employerUser($company, MembershipRole::Owner);

    fillJobForm(Livewire::actingAs($owner)->test('pages::employer.job-form', ['company' => $company]))
        ->call('saveAndPublish');

    expect(session('success'))->toStartWith('Sent for review.');

    $staff = staffWithTwoFactor();
    JobPosting::factory()->for($company)->pendingModeration()->count(Company::TRUSTED_AFTER_APPROVALS)->create()
        ->each(fn ($posting) => app(ApproveJobPosting::class)($posting, $staff));

    fillJobForm(Livewire::actingAs($owner)->test('pages::employer.job-form', ['company' => $company]), ['title' => 'Another Role'])
        ->call('saveAndPublish');

    expect(session('success'))->toBe('Job posting published.');
});

test('the company profile shows an open documents request until it is answered', function () {
    $company = Company::factory()->create(['verified_at' => null]);
    $owner = employerUser($company, MembershipRole::Owner);
    $staff = staffWithTwoFactor();

    app(RequestCompanyDocuments::class)($company, $staff, 'A trade licence, please.');

    $this->actingAs($owner)
        ->get(route('employer.company.edit', $company))
        ->assertSee('Documents requested')
        ->assertSee('A trade licence, please.');

    app(VerifyCompany::class)($company->fresh(), $staff);

    $this->get(route('employer.company.edit', $company))
        ->assertDontSee('Documents requested');
});
