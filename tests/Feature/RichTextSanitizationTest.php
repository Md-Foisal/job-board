<?php

use App\Models\Application;
use App\Models\Company;
use App\Models\ExperienceRecord;
use App\Models\JobPosting;

test('a script tag never survives being saved', function () {
    $company = Company::factory()->create([
        'description' => 'We hire engineers.<script>alert(1)</script>',
    ]);

    expect($company->fresh()->description)
        ->not->toContain('<script')
        ->and($company->fresh()->description)->toContain('We hire engineers.');
});

test('event handlers are stripped from tags that are otherwise allowed', function () {
    $job = JobPosting::factory()->create([
        'description' => '<p onclick="steal()">Join us</p>',
    ]);

    expect($job->fresh()->description)
        ->not->toContain('onclick')
        ->and($job->fresh()->description)->toContain('Join us');
});

test('a javascript link is not a link', function () {
    $job = JobPosting::factory()->create([
        'description' => '<p>See <a href="javascript:alert(1)">this</a></p>',
    ]);

    expect($job->fresh()->description)->not->toContain('javascript:');
});

test('the formatting people actually use is kept', function () {
    $job = JobPosting::factory()->create([
        'description' => '<h3>About the role</h3><p>You will <strong>build</strong> and <em>maintain</em>:</p><ul><li>APIs</li><li>Tests</li></ul>',
    ]);

    $saved = $job->fresh()->description;

    expect($saved)->toContain('<h3>')
        ->and($saved)->toContain('<strong>')
        ->and($saved)->toContain('<em>')
        ->and($saved)->toContain('<ul>')
        ->and($saved)->toContain('<li>APIs</li>');
});

test('an untouched editor saves nothing rather than an empty paragraph', function () {
    $job = JobPosting::factory()->create(['description' => '<p></p>']);

    expect($job->fresh()->description)->toBeNull();
});

test('cleaning applies to every field that takes formatted text', function () {
    $experience = ExperienceRecord::factory()->create([
        'description' => '<p>Led the team</p><iframe src="https://evil.test"></iframe>',
    ]);

    expect($experience->fresh()->description)
        ->toContain('Led the team')
        ->and($experience->fresh()->description)->not->toContain('<iframe');

    $application = Application::factory()->create([
        'cover_letter' => '<p>Hello</p><style>body{display:none}</style>',
    ]);

    expect($application->fresh()->cover_letter)
        ->toContain('Hello')
        ->and($application->fresh()->cover_letter)->not->toContain('<style');
});

test('cleaning happens on the attribute, so no writer can skip it', function () {
    // Not through a form or a controller -- straight at the model, the way
    // an import or a console command would write.
    $job = JobPosting::factory()->create();
    $job->description = '<p>Fine</p><script>bad()</script>';
    $job->save();

    expect($job->fresh()->description)->not->toContain('<script');
});
