<?php

namespace App\Actions;

use App\Enums\ApplicationOutcomeStatus;
use App\Enums\InvitationStatus;
use App\Enums\MembershipStatus;
use App\Models\ApplicationNote;
use App\Models\Invitation;
use App\Models\JobView;
use App\Models\ScreeningAnswer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Erases a person's personal data while keeping the record of what
 * happened -- claude/12's rule: anonymise, don't delete.
 *
 * What goes: everything that says who they were or what they wrote about
 * themselves -- name, email, photos, profile, preferences, education,
 * experience, every uploaded file, cover letters, screening answers, the
 * notes employers wrote about them, alerts, saved jobs, viewing history,
 * sessions and reset tokens.
 *
 * What stays: the rows other records point to, emptied -- the user row as
 * "Deleted user", the candidate profile, the applications with their
 * stages and history -- so a company's hiring numbers and the platform's
 * statistics do not change when someone leaves. Reports they filed and
 * decisions they took still point at the same, now anonymous, row.
 *
 * This overrides the application snapshot rule on purpose: the snapshot
 * protects an employer from a candidate quietly rewriting what was sent,
 * not from the candidate asking to be forgotten. Erasure is a legal
 * constraint (claude/13, Constraints) and sits above product rules.
 */
class AnonymizeUser
{
    public const NAME = 'Deleted user';

    public function __invoke(User $user): void
    {
        if ($user->anonymized_at !== null) {
            return;
        }

        /** @var list<array{0: string, 1: ?string}> $files [disk, path] */
        $files = [['public', $user->avatar]];

        DB::transaction(function () use ($user, &$files) {
            $originalEmail = $user->email;

            if ($profile = $user->candidateProfile) {
                $applicationIds = $profile->applications()->pluck('id');

                // An open application to someone who no longer exists is
                // withdrawn the same way the candidate would withdraw it,
                // history entry included, so the employer's pipeline is honest.
                foreach ($profile->applications()->where('outcome_status', ApplicationOutcomeStatus::Active)->get() as $application) {
                    $application->update(['outcome_status' => ApplicationOutcomeStatus::Withdrawn]);
                    $application->events()->create([
                        'changed_by_id' => $user->id,
                        'from_outcome_status' => ApplicationOutcomeStatus::Active->value,
                        'to_outcome_status' => ApplicationOutcomeStatus::Withdrawn->value,
                    ]);
                }

                DB::table('applications')->whereIn('id', $applicationIds)->update(['cover_letter' => null]);
                ScreeningAnswer::whereIn('application_id', $applicationIds)->update(['answer_text' => '']);
                ApplicationNote::whereIn('application_id', $applicationIds)->delete();

                foreach ($profile->documents()->withTrashed()->get() as $document) {
                    $files[] = ['local', $document->file_path];
                    $document->forceFill(['original_filename' => null])->save();
                    $document->delete();
                }

                $files[] = ['public', $profile->cover_photo_path];

                $profile->preference()->delete();
                $profile->educationRecords()->delete();
                $profile->experienceRecords()->delete();
                $profile->skills()->detach();
                $profile->forceFill([
                    'headline' => null,
                    'bio' => null,
                    'portfolio_url' => null,
                    'github_url' => null,
                    'linkedin_url' => null,
                    'cover_photo_path' => null,
                ])->save();
            }

            if ($recruiterProfile = $user->recruiterProfile) {
                $files[] = ['public', $recruiterProfile->avatar_path];
                $recruiterProfile->delete();
            }

            $user->memberships()->update(['status' => MembershipStatus::Inactive]);
            $user->jobAlerts()->delete();
            $user->savedJobs()->detach();
            JobView::where('user_id', $user->id)->delete();

            $anonymousEmail = "deleted-{$user->id}@anonymized.invalid";

            // Invitations addressed to them are company records, but the
            // address is theirs; an open one could only ever be accepted by
            // the person who is asking to be forgotten.
            Invitation::where('email', $originalEmail)
                ->where('status', InvitationStatus::Pending)
                ->update(['status' => InvitationStatus::Revoked]);
            Invitation::where('email', $originalEmail)->update(['email' => $anonymousEmail]);

            DB::table('password_reset_tokens')->where('email', $originalEmail)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();

            $user->forceFill([
                'name' => self::NAME,
                // .invalid is reserved (RFC 2606): it can never deliver mail
                // to a real person, and the id keeps the unique index happy.
                'email' => $anonymousEmail,
                'avatar' => null,
                'password' => Hash::make(Str::random(64)),
                'email_verified_at' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'remember_token' => null,
                'staff_role' => null,
                'anonymized_at' => now(),
            ])->save();

            if (! $user->trashed()) {
                $user->delete();
            }
        });

        // Files last, once the rows no longer point at them: a failed
        // transaction must not leave records whose files are already gone.
        foreach ($files as [$disk, $path]) {
            if ($path) {
                Storage::disk($disk)->delete($path);
            }
        }
    }
}
