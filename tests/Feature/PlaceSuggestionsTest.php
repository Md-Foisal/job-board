<?php

use App\Enums\WorkplaceType;
use App\Livewire\JobSearchAutocomplete;
use App\Models\JobPosting;
use App\Support\PlaceSuggestions;
use Livewire\Livewire;

function postingIn(string $city, WorkplaceType $workplace = WorkplaceType::Onsite): JobPosting
{
    return JobPosting::factory()->create(['location_city' => $city, 'workplace_type' => $workplace]);
}

it('offers Remote first, then the cities with the most open jobs', function () {
    postingIn('Berlin');
    postingIn('London');
    postingIn('London');
    postingIn('Lisbon', WorkplaceType::Remote);

    expect(array_column(PlaceSuggestions::for(''), 'label'))->toBe(['Remote', 'London', 'Berlin', 'Lisbon'])
        ->and(PlaceSuggestions::for('')[1]['jobs'])->toBe(2);
});

it('matches the start of a place name or of any word in it', function () {
    postingIn('London');
    postingIn('New York');
    postingIn('Lisbon');

    expect(array_column(PlaceSuggestions::for('lon'), 'label'))->toBe(['London'])
        ->and(array_column(PlaceSuggestions::for('york'), 'label'))->toBe(['New York'])
        ->and(PlaceSuggestions::for('don'))->toBe([]);
});

it('never offers a place whose jobs are all closed or waiting for review', function () {
    JobPosting::factory()->closed()->create(['location_city' => 'Oslo']);
    JobPosting::factory()->pendingModeration()->create(['location_city' => 'Oslo']);
    postingIn('Paris');

    expect(array_column(PlaceSuggestions::for('o'), 'label'))->toBe([])
        ->and(array_column(PlaceSuggestions::for(''), 'label'))->toBe(['Paris']);
});

it('shows the places under the home page where box and fills it with the one picked', function () {
    postingIn('Manchester');

    Livewire::test(JobSearchAutocomplete::class, ['variant' => 'hero'])
        ->set('where', 'man')
        ->assertSee('Places with open jobs')
        ->assertSee('Manchester')
        ->set('where', 'Remote')
        ->call('goToSearch')
        ->assertRedirect(route('jobs.index', ['workplaceType' => WorkplaceType::Remote->value]));
});

it('shows the places under the search page where box too', function () {
    postingIn('Leeds');

    $this->get(route('jobs.index'))
        ->assertOk()
        ->assertSee('Places with open jobs')
        ->assertSee('Leeds');
});
