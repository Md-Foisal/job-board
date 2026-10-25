<?php

use App\Models\CoverLetter;
use App\Models\ExperienceRecord;
use App\Models\JobPosting;
use App\Models\Skill;
use App\Support\CoverLetterDraft;
use Livewire\Livewire;

function letterCandidate()
{
    $candidate = candidateUser();
    $candidate->forceFill(['name' => 'Nusrat Jahan'])->save();
    $profile = $candidate->candidateProfile;
    $profile->update(['headline' => 'Laravel developer']);
    $profile->skills()->attach(Skill::firstOrCreate(['name' => 'Laravel']), ['proficiency' => 'advanced']);
    $profile->skills()->attach(Skill::firstOrCreate(['name' => 'Figma']), ['proficiency' => 'beginner']);
    ExperienceRecord::factory()->for($profile)->create([
        'job_title' => 'Backend Developer', 'company_name' => 'Acme', 'start_date' => '2023-01-01', 'end_date' => null,
        'description' => '<ul><li>Built the Laravel payments API</li><li>Mentored two interns</li></ul>',
    ]);

    return $candidate->fresh();
}

function savedJobFor($candidate): JobPosting
{
    $job = JobPosting::factory()->create(['title' => 'Laravel Developer']);
    $job->skills()->attach(Skill::firstOrCreate(['name' => 'Laravel']), ['importance' => 'required']);
    $candidate->savedJobs()->attach($job);

    return $job->fresh();
}

test('the draft is built from the profile and the job, claiming nothing new', function () {
    $candidate = letterCandidate();
    $job = savedJobFor($candidate);

    $draft = CoverLetterDraft::write($candidate->candidateProfile, $job, null, null, 'Ms Rahman');

    expect($draft)
        ->toStartWith('Dear Ms Rahman,')
        ->toContain('I am writing to apply for the Laravel Developer position at '.$job->company->name.'. I am a laravel developer.')
        ->toContain('In my work I use Laravel, which this role asks for.')
        ->toContain('At Acme, as Backend Developer, I built the Laravel payments API.')
        ->toEndWith("Sincerely,\nNusrat Jahan")
        ->not->toContain('Figma, which');
});

test('opening the builder for a job makes its letter once, with a draft', function () {
    $candidate = letterCandidate();
    $job = savedJobFor($candidate);

    Livewire::actingAs($candidate)->withQueryParams(['job' => $job->slug])->test('pages::candidate.cover-letters')
        ->assertSet('jobId', (string) $job->id)
        ->assertSee('Laravel Developer');
    Livewire::actingAs($candidate)->withQueryParams(['job' => $job->slug])->test('pages::candidate.cover-letters');

    expect($candidate->candidateProfile->coverLetters()->sole()->body)->toContain('Dear Hiring Team,');
});

test('a job the candidate has neither saved nor applied to gets no letter made for it', function () {
    $candidate = letterCandidate();
    $job = JobPosting::factory()->create();

    Livewire::actingAs($candidate)->withQueryParams(['job' => $job->slug])->test('pages::candidate.cover-letters')
        ->assertSee('No cover letters yet');

    expect(CoverLetter::count())->toBe(0);
});

test('a letter for a job elsewhere is written, saved as it changes, and downloaded as a PDF', function () {
    $candidate = letterCandidate();

    $page = Livewire::actingAs($candidate)->test('pages::candidate.cover-letters')
        ->call('newLetter')
        ->set('companyName', 'Globex')
        ->set('roleTitle', 'PHP Developer')
        ->set('recipient', 'Mr Karim')
        ->call('draft');

    $letter = $candidate->candidateProfile->coverLetters()->sole();
    expect($letter->company_name)->toBe('Globex')
        ->and($letter->body)->toStartWith('Dear Mr Karim,')->toContain('PHP Developer position at Globex');

    expect($page->instance()->preview)->toContain('Application for PHP Developer')->toContain('Globex');

    $page->call('download')->assertFileDownloaded('Nusrat-Jahan-Cover-Letter.pdf');
});

test('a letter is limited in length, and its job must be one of the candidate\'s', function () {
    $candidate = letterCandidate();
    $stranger = JobPosting::factory()->create();

    Livewire::actingAs($candidate)->test('pages::candidate.cover-letters')
        ->call('newLetter')
        ->set('body', str_repeat('a', CoverLetter::BODY_MAX + 1))
        ->assertHasErrors('body')
        ->set('jobId', (string) $stranger->id)
        ->assertHasErrors('jobId');
});

test('another candidate\'s letter cannot be opened', function () {
    $other = CoverLetter::factory()->create(['body' => 'Private letter']);

    Livewire::actingAs(letterCandidate())->test('pages::candidate.cover-letters')
        ->call('open', $other->id)
        ->assertDontSee('Private letter');
});

test('the application form is filled from the letter written for that job', function () {
    $candidate = letterCandidate();
    $job = savedJobFor($candidate);
    CoverLetter::factory()->for($candidate->candidateProfile)->create(['job_posting_id' => $job->id, 'body' => "Dear team,\n\nI <3 queues."]);

    Livewire::actingAs($candidate)->test('pages::job-apply', ['jobPosting' => $job])
        ->assertSet('coverLetter', '<p>Dear team,</p><p>I &lt;3 queues.</p>')
        ->assertSee('Filled from the cover letter you wrote for this job.');
});

test('cover letters are in the candidate\'s navigation', function () {
    $this->actingAs(letterCandidate())->get(route('candidate.cover-letters'))
        ->assertOk()
        ->assertSee('Cover letters');
});
