<?php

use App\Actions\AnonymizeUser;
use App\Enums\ApplicationOutcomeStatus;
use App\Enums\InvitationStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Application;
use App\Models\ApplicationNote;
use App\Models\CandidatePreference;
use App\Models\Company;
use App\Models\Document;
use App\Models\EducationRecord;
use App\Models\ExperienceRecord;
use App\Models\Invitation;
use App\Models\JobAlert;
use App\Models\JobPosting;
use App\Models\JobView;
use App\Models\RecruiterProfile;
use App\Models\ScreeningAnswer;
use App\Models\ScreeningQuestion;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A candidate with something in every place personal data can live.
 */
function candidateWithEverything(): array
{
    Storage::fake('local');
    Storage::fake('public');

    $user = candidateUser();
    $user->forceFill(['name' => 'Sakib Hasan', 'email' => 'sakib@example.com', 'avatar' => 'avatars/sakib.jpg'])->save();
    Storage::disk('public')->put('avatars/sakib.jpg', 'x');

    $profile = $user->candidateProfile;
    $profile->forceFill(['headline' => 'Laravel developer', 'bio' => 'I live in Dhaka.', 'cover_photo_path' => 'covers/sakib.jpg'])->save();
    Storage::disk('public')->put('covers/sakib.jpg', 'x');

    CandidatePreference::factory()->for($profile)->create();
    EducationRecord::factory()->for($profile)->create();
    ExperienceRecord::factory()->for($profile)->create();

    $posting = JobPosting::factory()->create();
    $question = ScreeningQuestion::forceCreate(['job_posting_id' => $posting->id, 'question_text' => 'Where do you live?', 'display_order' => 1]);
    $application = Application::factory()->create(['job_posting_id' => $posting->id, 'candidate_profile_id' => $profile->id]);
    ScreeningAnswer::forceCreate(['application_id' => $application->id, 'screening_question_id' => $question->id, 'answer_text' => 'Mirpur, Dhaka']);
    ApplicationNote::forceCreate(['application_id' => $application->id, 'author_id' => employerUser($posting->company)->id, 'note' => 'Sakib seemed nervous.']);
    Storage::disk('local')->put($application->resumeDocument->file_path, 'cv');

    JobAlert::factory()->for($user)->create();
    $user->savedJobs()->attach($posting->id);
    JobView::forceCreate(['user_id' => $user->id, 'job_posting_id' => $posting->id, 'viewed_at' => now()]);
    DB::table('password_reset_tokens')->insert(['email' => 'sakib@example.com', 'token' => 'x', 'created_at' => now()]);
    DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    return [$user->fresh(), $application];
}

test('a candidate is emptied of everything personal while the application stays on the record', function () {
    [$user, $application] = candidateWithEverything();
    $cvPath = $application->resumeDocument->file_path;

    app(AnonymizeUser::class)($user);

    $user = User::withTrashed()->find($user->id);
    $profile = $user->candidateProfile;
    $application->refresh();

    expect($user->name)->toBe('Deleted user')
        ->and($user->email)->toBe("deleted-{$user->id}@anonymized.invalid")
        ->and($user->avatar)->toBeNull()
        ->and($user->anonymized_at)->not->toBeNull()
        ->and($user->trashed())->toBeTrue()
        ->and($profile->headline)->toBeNull()
        ->and($profile->bio)->toBeNull()
        ->and($profile->cover_photo_path)->toBeNull()
        ->and(CandidatePreference::count())->toBe(0)
        ->and(EducationRecord::count())->toBe(0)
        ->and(ExperienceRecord::count())->toBe(0)
        ->and(JobAlert::count())->toBe(0)
        ->and(JobView::count())->toBe(0)
        ->and($user->savedJobs()->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);

    // The record of the application survives, emptied.
    expect($application->exists)->toBeTrue()
        ->and($application->cover_letter)->toBeNull()
        ->and($application->outcome_status)->toBe(ApplicationOutcomeStatus::Withdrawn)
        ->and($application->events()->where('to_outcome_status', 'withdrawn')->exists())->toBeTrue()
        ->and(ScreeningAnswer::sole()->answer_text)->toBe('')
        ->and(ApplicationNote::count())->toBe(0)
        ->and(Document::withTrashed()->sole()->original_filename)->toBeNull();

    Storage::disk('local')->assertMissing($cvPath);
    Storage::disk('public')->assertMissing('avatars/sakib.jpg');
    Storage::disk('public')->assertMissing('covers/sakib.jpg');
});

test('an application already decided keeps its outcome', function () {
    [$user, $application] = candidateWithEverything();
    $application->update(['outcome_status' => ApplicationOutcomeStatus::Hired]);

    app(AnonymizeUser::class)($user);

    expect($application->fresh()->outcome_status)->toBe(ApplicationOutcomeStatus::Hired);
});

test('an employer leaves their companies, and what they did stays attributed to an anonymous row', function () {
    Storage::fake('public');
    $company = Company::factory()->create();
    $recruiter = employerUser($company, MembershipRole::Manager);
    RecruiterProfile::factory()->for($recruiter)->create(['avatar_path' => 'recruiters/me.jpg']);
    Storage::disk('public')->put('recruiters/me.jpg', 'x');
    $posting = JobPosting::factory()->for($company)->create(['posted_by_id' => $recruiter->id]);

    app(AnonymizeUser::class)($recruiter);

    expect($recruiter->memberships()->sole()->status)->toBe(MembershipStatus::Inactive)
        ->and(RecruiterProfile::count())->toBe(0)
        ->and($posting->fresh()->posted_by_id)->toBe($recruiter->id)
        ->and(JobPosting::find($posting->id))->not->toBeNull();

    Storage::disk('public')->assertMissing('recruiters/me.jpg');
});

test('invitations to their address stop working and stop naming them', function () {
    $user = candidateUser();
    $user->forceFill(['email' => 'nadia@example.com'])->save();
    $open = Invitation::factory()->create(['email' => 'nadia@example.com']);
    $accepted = Invitation::factory()->accepted()->create(['email' => 'nadia@example.com']);

    app(AnonymizeUser::class)($user);

    expect($open->fresh()->status)->toBe(InvitationStatus::Revoked)
        ->and($accepted->fresh()->status)->toBe(InvitationStatus::Accepted)
        ->and(Invitation::where('email', 'nadia@example.com')->exists())->toBeFalse();
});

test('an erased account cannot sign in, and its old address is free to register again', function () {
    $user = candidateUser();
    $user->forceFill(['email' => 'sakib@example.com', 'password' => 'password'])->save();

    app(AnonymizeUser::class)($user);

    $this->post(route('login.store'), ['email' => 'sakib@example.com', 'password' => 'password']);
    $this->assertGuest();

    expect(User::withTrashed()->where('email', 'sakib@example.com')->exists())->toBeFalse();
});

test('running it twice changes nothing the second time', function () {
    $user = candidateUser();
    app(AnonymizeUser::class)($user);
    $first = User::withTrashed()->find($user->id)->anonymized_at;

    $this->travel(1)->day();
    app(AnonymizeUser::class)(User::withTrashed()->find($user->id));

    expect(User::withTrashed()->find($user->id)->anonymized_at->equalTo($first))->toBeTrue();
});

test('the nightly sweep erases only accounts past the grace period', function () {
    $pastGrace = candidateUser();
    $pastGrace->delete();
    $withinGrace = candidateUser();
    $active = candidateUser();

    $this->travel(User::DELETION_GRACE_DAYS)->days();
    $withinGrace->delete();
    $this->travel(1)->minute();

    $this->artisan('users:anonymize-deleted')->assertSuccessful();

    expect(User::withTrashed()->find($pastGrace->id)->anonymized_at)->not->toBeNull()
        ->and(User::withTrashed()->find($withinGrace->id)->anonymized_at)->toBeNull()
        ->and($active->fresh()->anonymized_at)->toBeNull()
        ->and($active->fresh()->name)->not->toBe('Deleted user');
});

test('the sweep runs every night', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'users:anonymize-deleted'));

    expect($event?->expression)->toBe('0 3 * * *')
        ->and($event->timezone)->toBe('Asia/Dhaka');
});
