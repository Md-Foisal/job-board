<?php

use App\Enums\DocumentType;
use App\Models\Application;
use App\Models\Document;
use App\Models\JobPosting;
use App\Models\Skill;
use App\Models\User;

function skilledCandidate(array $skills): User
{
    $candidate = candidateUser();
    $candidate->candidateProfile->skills()->attach(
        collect($skills)->mapWithKeys(fn (Skill $skill) => [$skill->id => ['proficiency' => 'advanced']])->all()
    );

    return $candidate;
}

function postingAsking(array $skills, array $attributes = []): JobPosting
{
    $posting = JobPosting::factory()->create($attributes);
    $posting->skills()->attach(
        collect($skills)->mapWithKeys(fn (Skill $skill) => [$skill->id => ['importance' => 'required']])->all()
    );

    return $posting;
}

test('the dashboard shows the open jobs that fit, best fit first, with the score', function () {
    $laravel = Skill::create(['name' => 'Laravel']);
    $vue = Skill::create(['name' => 'Vue']);
    $go = Skill::create(['name' => 'Go']);
    $candidate = skilledCandidate([$laravel, $vue]);

    postingAsking([$laravel, $go], ['title' => 'Half Fit Role']);
    postingAsking([$laravel, $vue], ['title' => 'Full Fit Role']);
    postingAsking([$go], ['title' => 'No Overlap Role']);

    $this->actingAs($candidate)
        ->get(route('candidate.dashboard'))
        ->assertSeeInOrder(['Jobs that match your skills', 'Full Fit Role', '100%', 'Half Fit Role', '50%'])
        ->assertDontSee('No Overlap Role');
});

test('jobs already applied to and jobs the public cannot see are left out', function () {
    $laravel = Skill::create(['name' => 'Laravel']);
    $candidate = skilledCandidate([$laravel]);

    $applied = postingAsking([$laravel], ['title' => 'Applied Role']);
    postingAsking([$laravel], ['title' => 'Waiting Role'])->forceFill(['moderation_status' => 'pending'])->save();

    Application::factory()->create([
        'job_posting_id' => $applied->id,
        'candidate_profile_id' => $candidate->candidateProfile->id,
        'resume_document_id' => Document::factory()->create([
            'candidate_profile_id' => $candidate->candidateProfile->id,
            'document_type' => DocumentType::Cv,
        ])->id,
    ]);

    $this->actingAs($candidate)
        ->get(route('candidate.dashboard'))
        ->assertDontSee('Applied Role')
        ->assertDontSee('Waiting Role')
        ->assertSee('No open job asks for your skills right now.');
});

test('a candidate with no skills is asked to add them, not shown guesses', function () {
    postingAsking([Skill::create(['name' => 'Laravel'])], ['title' => 'Some Role']);

    $this->actingAs(candidateUser())
        ->get(route('candidate.dashboard'))
        ->assertSee('Add your skills and we will show the open jobs that fit you')
        ->assertDontSee('Some Role');
});
