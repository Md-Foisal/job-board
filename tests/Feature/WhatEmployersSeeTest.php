<?php

use App\Enums\WorkplaceType;
use App\Models\Application;
use App\Models\CandidatePreference;
use App\Models\Company;
use App\Models\EducationRecord;
use App\Models\ExperienceRecord;
use App\Models\JobPosting;

test('the preference form makes no promise about what employers see', function () {
    $this->actingAs(candidateUser())
        ->get(route('candidate.preferences.edit'))
        ->assertOk()
        ->assertSee('Only you see them.')
        ->assertDontSee('Actively searching')
        ->assertDontSee('Shows employers');
});

test('a candidate saves their preferences', function () {
    $user = candidateUser();

    $this->actingAs($user)
        ->patch(route('candidate.preferences.update'), [
            'desired_salary_min' => 3000,
            'desired_salary_max' => 4500,
            'desired_salary_currency' => 'EUR',
            'preferred_workplace_type' => WorkplaceType::Remote->value,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $preference = $user->candidateProfile->preference()->first();

    expect($preference->desired_salary_min)->toBe(3000)
        ->and($preference->desired_salary_max)->toBe(4500)
        ->and($preference->desired_salary_currency)->toBe('EUR')
        ->and($preference->preferred_workplace_type)->toBe(WorkplaceType::Remote);
});

test('the profile page says exactly what a company sees', function () {
    // fresh(): the page reads avatar, which a factory-made user does not
    // carry until it is loaded from the database.
    $this->actingAs(candidateUser()->fresh())
        ->get(route('candidate.profile.edit'))
        ->assertOk()
        ->assertSee('When you apply, the company sees this profile, apart from your contact details and cover photo, with the CV you attach.')
        ->assertDontSee('look you up');
});

test('the company reads the profile an application points at, history and links included', function () {
    $company = Company::factory()->create();
    $candidate = candidateUser();
    $profile = $candidate->candidateProfile;
    $profile->update([
        'headline' => 'Support lead',
        'github_url' => 'https://github.com/rafi-demo',
        'linkedin_url' => 'https://www.linkedin.com/in/rafi-demo/',
    ]);
    ExperienceRecord::factory()->for($profile)->create([
        'job_title' => 'Customer Support Lead',
        'company_name' => 'Brightdesk Ltd',
        'start_date' => '2022-03-01',
        'end_date' => null,
    ]);
    ExperienceRecord::factory()->for($profile)->create([
        'job_title' => 'Support Agent',
        'company_name' => 'Callfield',
        'start_date' => '2019-01-01',
        'end_date' => '2022-02-01',
    ]);
    EducationRecord::factory()->for($profile)->create([
        'institution_name' => 'University of Dhaka',
        'degree' => 'BA',
        'field_of_study' => 'English',
    ]);
    $application = Application::factory()->create([
        'job_posting_id' => JobPosting::factory()->for($company)->create()->id,
        'candidate_profile_id' => $profile->id,
    ]);

    $this->actingAs(employerUser($company))
        ->get(route('employer.applications.show', ['company' => $company, 'application' => $application]))
        ->assertOk()
        ->assertSeeInOrder(['Customer Support Lead', 'Brightdesk Ltd', 'Present', 'Support Agent', 'Callfield'])
        ->assertSee('University of Dhaka')
        ->assertSee('BA, English')
        ->assertSee('github.com/rafi-demo')
        ->assertSee('linkedin.com/in/rafi-demo')
        ->assertSee('href="https://github.com/rafi-demo"', false)
        ->assertSee('rel="noopener noreferrer nofollow"', false);
});

test('an empty history says so instead of leaving a gap', function () {
    $company = Company::factory()->create();
    $application = Application::factory()->create([
        'job_posting_id' => JobPosting::factory()->for($company)->create()->id,
        'candidate_profile_id' => candidateUser()->candidateProfile->id,
    ]);

    $this->actingAs(employerUser($company))
        ->get(route('employer.applications.show', ['company' => $company, 'application' => $application]))
        ->assertOk()
        ->assertSee('No work experience on their profile.')
        ->assertSee('No education on their profile.');
});

test('the company never sees the contact details or the preferences on a profile', function () {
    $company = Company::factory()->create();
    $profile = candidateUser()->candidateProfile;
    $profile->update(['phone' => '+44 7700 900456', 'location' => 'Lalmonirhat, Bangladesh']);
    CandidatePreference::factory()->for($profile)->create([
        'desired_salary_min' => 98765,
        'desired_salary_max' => 123456,
    ]);
    $application = Application::factory()->create([
        'job_posting_id' => JobPosting::factory()->for($company)->create()->id,
        'candidate_profile_id' => $profile->id,
    ]);

    $this->actingAs(employerUser($company))
        ->get(route('employer.applications.show', ['company' => $company, 'application' => $application]))
        ->assertOk()
        ->assertDontSee('7700 900456')
        ->assertDontSee('Lalmonirhat')
        ->assertDontSee('98,765')
        ->assertDontSee('98765')
        ->assertDontSee('123,456');
});
