<?php

use App\Livewire\SaveJobButton;
use App\Models\JobPosting;
use Livewire\Livewire;

test('a guest who tries to save a job is sent to log in', function () {
    Livewire::test(SaveJobButton::class, ['jobPosting' => JobPosting::factory()->create()])
        ->call('toggle')
        ->assertRedirect(route('login'));

    // No page to go back to, so signing in lands where it always does.
    expect(session('url.intended'))->toBeNull();
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

test('a guest who signs in to save comes back to the page they were on', function () {
    $page = route('jobs.index', ['q' => 'Laravel', 'workplaceType' => 'remote']);
    session()->setPreviousUrl($page);

    Livewire::test(SaveJobButton::class, ['jobPosting' => JobPosting::factory()->create()])
        ->call('toggle')
        ->assertRedirect(route('login'));

    expect(session('url.intended'))->toBe($page);
});

test('a guest is never sent on to another site after signing in', function () {
    session()->setPreviousUrl('https://example.com/elsewhere');

    Livewire::test(SaveJobButton::class, ['jobPosting' => JobPosting::factory()->create()])
        ->call('toggle')
        ->assertRedirect(route('login'));

    expect(session('url.intended'))->toBeNull();
});

test('a list that already knows a job is saved says so without asking again', function () {
    $job = JobPosting::factory()->create();

    Livewire::actingAs(candidateUser())
        ->test(SaveJobButton::class, ['jobPosting' => $job, 'initiallySaved' => true])
        ->assertSet('saved', true)
        ->call('toggle')
        ->assertSet('saved', false);
});
