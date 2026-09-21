<?php

use Livewire\Livewire;

test('a form over the server size limit gets a page that says so and leads back to it', function () {
    $candidate = candidateUser();

    $this->actingAs($candidate)
        ->from(route('candidate.profile.edit'))
        ->call('PATCH', route('candidate.profile.update'), server: [
            // Far over any post_max_size a server would set.
            'CONTENT_LENGTH' => 10 * 1024 * 1024 * 1024,
        ])
        ->assertStatus(413)
        ->assertSee('That upload was too large')
        ->assertSee(route('candidate.profile.edit'));
});

test('an upload Livewire could not deliver is explained on the field itself', function () {
    Livewire::actingAs(candidateUser())
        ->test('pages::candidate.documents')
        ->call('_uploadErrored', 'file', null, false)
        ->assertHasErrors(['file' => 'The file could not be uploaded. It may be larger than we accept — check the size limit shown with the field.']);
});
