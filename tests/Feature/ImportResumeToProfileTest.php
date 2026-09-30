<?php

use App\Actions\ImportResumeToProfile;
use App\Enums\ProficiencyLevel;
use App\Models\ExperienceRecord;
use App\Models\Skill;

function importResume(array $selection, $candidateProfile): array
{
    return app(ImportResumeToProfile::class)($candidateProfile, $selection);
}

test('chosen profile fields are written, and nothing outside them', function () {
    $profile = candidateUser()->candidateProfile;
    $profile->update(['headline' => 'Old headline']);

    $added = importResume(['profile' => [
        'headline' => 'Laravel developer',
        'linkedin_url' => 'https://www.linkedin.com/in/karim-rahman',
        'user_id' => 999,
    ]], $profile);

    $profile->refresh();

    expect($profile->headline)->toBe('Laravel developer')
        ->and($profile->linkedin_url)->toBe('https://www.linkedin.com/in/karim-rahman')
        ->and($profile->user_id)->not->toBe(999)
        ->and($added['profile'])->toBe(2);
});

test('new skills start at intermediate, and a skill already held keeps its level', function () {
    $profile = candidateUser()->candidateProfile;
    $laravel = Skill::create(['name' => 'Laravel']);
    $php = Skill::create(['name' => 'PHP']);
    $profile->skills()->attach($laravel->id, ['proficiency' => ProficiencyLevel::Advanced->value]);

    $added = importResume(['skill_ids' => [$laravel->id, $php->id]], $profile);

    $levels = $profile->skills()->get()->mapWithKeys(fn ($skill) => [$skill->name => $skill->pivot->proficiency]);

    expect($levels->all())->toBe(['Laravel' => ProficiencyLevel::Advanced, 'PHP' => ProficiencyLevel::Intermediate])
        ->and($added['skills'])->toBe(1);
});

test('a skill staff removed from the list, or one that never existed, is not added', function () {
    $profile = candidateUser()->candidateProfile;
    $removed = Skill::create(['name' => 'Lumen']);
    $removed->delete();

    $added = importResume(['skill_ids' => [$removed->id, 9999]], $profile);

    expect($profile->skills()->count())->toBe(0)
        ->and($added['skills'])->toBe(0);
});

test('a role already on the profile is recognised by company, title and start month', function () {
    $profile = candidateUser()->candidateProfile;
    ExperienceRecord::factory()->for($profile)->create([
        'company_name' => 'Acme Ltd',
        'job_title' => 'Backend Developer',
        'start_date' => '2021-03-15',
    ]);

    $added = importResume(['experience' => [
        ['company_name' => ' acme  ltd', 'job_title' => 'BACKEND developer', 'description' => null, 'start_date' => '2021-03-01', 'end_date' => null],
        ['company_name' => 'Acme Ltd', 'job_title' => 'Backend Developer', 'description' => null, 'start_date' => '2019-01-01', 'end_date' => '2021-02-01'],
        ['company_name' => 'Beta Inc', 'job_title' => 'Intern', 'description' => 'PHP work', 'start_date' => '2018-06-01', 'end_date' => '2018-12-01'],
    ]], $profile);

    expect($added['experience'])->toBe(2)
        ->and($profile->experienceRecords()->count())->toBe(3);
});

test('a course already on the profile is recognised by institution, degree and start month', function () {
    $profile = candidateUser()->candidateProfile;
    $profile->educationRecords()->create([
        'institution_name' => 'University of Dhaka',
        'degree' => 'BSc',
        'start_date' => '2015-01-01',
    ]);

    $entries = [
        ['institution_name' => 'University of Dhaka', 'degree' => 'bsc', 'field_of_study' => 'CSE', 'start_date' => '2015-01-20', 'end_date' => '2019-01-01'],
        ['institution_name' => 'University of Dhaka', 'degree' => 'MSc', 'field_of_study' => 'CSE', 'start_date' => '2019-07-01', 'end_date' => null],
    ];

    expect(importResume(['education' => $entries], $profile)['education'])->toBe(1)
        ->and(importResume(['education' => $entries], $profile)['education'])->toBe(0)
        ->and($profile->educationRecords()->count())->toBe(2);
});

test('one entry chosen twice in the same import is added once', function () {
    $profile = candidateUser()->candidateProfile;
    $role = ['company_name' => 'Acme Ltd', 'job_title' => 'Developer', 'description' => null, 'start_date' => '2020-01-01', 'end_date' => null];

    expect(importResume(['experience' => [$role, $role]], $profile)['experience'])->toBe(1);
});

test('the import is all or nothing', function () {
    $profile = candidateUser()->candidateProfile;
    $profile->update(['headline' => 'Old headline']);
    $skill = Skill::create(['name' => 'Laravel']);

    expect(fn () => importResume([
        'profile' => ['headline' => 'Laravel developer'],
        'skill_ids' => [$skill->id],
        'education' => [['institution_name' => 'University of Dhaka', 'degree' => null, 'field_of_study' => null, 'start_date' => 'not a date', 'end_date' => null]],
    ], $profile))->toThrow(Exception::class);

    expect($profile->refresh()->headline)->toBe('Old headline')
        ->and($profile->skills()->count())->toBe(0);
});
