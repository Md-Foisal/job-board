<?php

use App\Livewire\SaveJobButton;
use App\Models\JobPosting;
use Livewire\Livewire;

test('a guest who tries to save a job is sent to log in', function () {
    Livewire::test(SaveJobButton::class, ['jobPosting' => JobPosting::factory()->create()])
        ->call('toggle')
        ->assertRedirect(route('login'));
});

test('a candidate saves a job and takes it back off the list', function () {
    $candidate = candidateUser();
    $job = JobPosting::factory()->create();

    $button = Livewire::actingAs($candidate)
        ->test(SaveJobButton::class, ['jobPosting' => $job])
        ->assertSet('saved', false)
        ->call('toggle')
        ->assertSet('saved', true);

    expect($candidate->savedJobs()->whereKey($job->id)->exists())->toBeTrue();

    $button->call('toggle')->assertSet('saved', false);

    expect($candidate->savedJobs()->whereKey($job->id)->exists())->toBeFalse();
});

test('the card button keeps one name and reports whether the job is saved', function () {
    $candidate = candidateUser();
    $job = JobPosting::factory()->create();
    $candidate->savedJobs()->attach($job->id);

    Livewire::actingAs($candidate)
        ->test(SaveJobButton::class, ['jobPosting' => $job, 'compact' => true])
        ->assertSeeHtml('aria-label="Save job"')
        ->assertSeeHtml('aria-pressed="true"')
        ->call('toggle')
        ->assertSeeHtml('aria-label="Save job"')
        ->assertSeeHtml('aria-pressed="false"');
});

test('the job page button says whether the job is saved', function () {
    $candidate = candidateUser();
    $job = JobPosting::factory()->create();

    Livewire::actingAs($candidate)
        ->test(SaveJobButton::class, ['jobPosting' => $job])
        ->assertSee('Save')
        ->call('toggle')
        ->assertSee('Saved');
});
