<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Support\SubmissionLimits;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * Note: 'role' is validated but not persisted on the user — candidate/
     * employer status is derived from relationships (CandidateProfile,
     * Membership), not stored directly. Choosing 'candidate' does create an
     * empty CandidateProfile here, so that relationship exists from the
     * moment of registration. Choosing 'employer' does not create a Company
     * here, since a company needs a name/slug that this form doesn't
     * collect — that happens in a separate company-creation step.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        // Checked before validation so someone who is blocked is told so
        // straight away, rather than after correcting every field.
        $limitKey = SubmissionLimits::registrationKey(request()->ip());

        if (RateLimiter::tooManyAttempts($limitKey, SubmissionLimits::REGISTRATIONS_PER_HOUR)) {
            throw ValidationException::withMessages([
                'email' => __('Too many accounts have been created from this network recently. Please try again in :minutes minutes.', [
                    'minutes' => SubmissionLimits::minutesUntilAvailable($limitKey),
                ]),
            ]);
        }

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'role' => ['required', 'in:candidate,employer'],
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        if ($input['role'] === 'candidate') {
            $user->candidateProfile()->create([]);
        }

        RateLimiter::hit($limitKey, 3600);

        return $user;
    }
}
