<?php

use App\Models\JobPosting;
use Livewire\Livewire;

test('guest sees active job postings on the search page', function () {
    JobPosting::factory()->create(['title' => 'Backend Engineer']);
    JobPosting::factory()->draft()->create(['title' => 'Hidden Draft Role']);
    JobPosting::factory()->pendingModeration()->create(['title' => 'Awaiting Review Role']);

    $response = $this->get(route('jobs.index'));

    $response->assertOk();
    $response->assertSee('Backend Engineer');
    $response->assertDontSee('Hidden Draft Role');
    $response->assertDontSee('Awaiting Review Role');
});

test('keyword filter narrows results by job title', function () {
    JobPosting::factory()->create(['title' => 'Senior Laravel Developer']);
    JobPosting::factory()->create(['title' => 'Marketing Manager']);

    Livewire::test('pages::job-search')
        ->set('q', 'Laravel')
        ->assertSee('Senior Laravel Developer')
        ->assertDontSee('Marketing Manager');
});
