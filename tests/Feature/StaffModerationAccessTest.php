<?php

use App\Enums\AccountStatus;
use App\Enums\MembershipRole;
use App\Enums\StaffRole;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Membership;
use App\Models\Report;
use App\Models\User;

it('lets staff moderate a posting from a company they have nothing to do with', function () {
    $posting = JobPosting::factory()->create();

    expect(staffUser()->can('moderate', $posting))->toBeTrue();
});

it('stops staff moderating a posting from a company they work for', function () {
    $company = Company::factory()->create();
    $posting = JobPosting::factory()->for($company)->create();

    $staff = staffUser();
    Membership::factory()->for($staff)->for($company)->create([
        'role' => MembershipRole::Member,
    ]);

    expect($staff->fresh()->can('moderate', $posting))->toBeFalse();
});

it('strips a suspended staff member of their moderation powers', function () {
    $posting = JobPosting::factory()->create();

    $staff = staffUser();
    $staff->account_status = AccountStatus::Suspended;
    $staff->save();

    expect($staff->can('moderate', $posting))->toBeFalse();
});

it('gives an ordinary user no moderation powers at all', function () {
    $posting = JobPosting::factory()->create();
    $company = Company::factory()->create();

    $user = candidateUser();

    expect($user->can('moderate', $posting))->toBeFalse()
        ->and($user->can('moderate', $company))->toBeFalse();
});

it('recuses staff from reports that reach their own employer', function () {
    $company = Company::factory()->create();
    $posting = JobPosting::factory()->for($company)->create();

    $staff = staffUser();
    Membership::factory()->for($staff)->for($company)->create([
        'role' => MembershipRole::Member,
    ]);
    $staff = $staff->fresh();

    $aboutPosting = new Report;
    $aboutPosting->setRelation('reportable', $posting);

    $aboutCompany = new Report;
    $aboutCompany->setRelation('reportable', $company);

    expect($staff->can('moderate', $aboutPosting))->toBeFalse()
        ->and($staff->can('moderate', $aboutCompany))->toBeFalse();
});

it('keeps a report actionable when its subject is gone', function () {
    $orphan = new Report;
    $orphan->setRelation('reportable', null);

    expect(staffUser()->can('moderate', $orphan))->toBeTrue();
});

it('reserves suspending an account for super admins', function () {
    $target = User::factory()->create();

    expect(staffUser(StaffRole::Moderator)->can('suspend', $target))->toBeFalse()
        ->and(staffUser(StaffRole::SuperAdmin)->can('suspend', $target))->toBeTrue();
});

it('will not let a super admin suspend themselves', function () {
    $admin = staffUser(StaffRole::SuperAdmin);

    expect($admin->can('suspend', $admin))->toBeFalse();
});

it('will not let a super admin suspend a peer, but lets them suspend a moderator', function () {
    $admin = staffUser(StaffRole::SuperAdmin);

    expect($admin->can('suspend', staffUser(StaffRole::SuperAdmin)))->toBeFalse()
        ->and($admin->can('suspend', staffUser(StaffRole::Moderator)))->toBeTrue();
});

it('lets super admins reinstate each other, so no one stays locked out', function () {
    $admin = staffUser(StaffRole::SuperAdmin);
    $peer = staffUser(StaffRole::SuperAdmin);

    expect($admin->can('reinstate', $peer))->toBeTrue()
        ->and(staffUser(StaffRole::Moderator)->can('reinstate', $peer))->toBeFalse();
});
