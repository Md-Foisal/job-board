<?php

test('profile links are saved when they are web addresses', function () {
    $candidate = candidateUser()->fresh();

    $this->actingAs($candidate)
        ->patch(route('candidate.profile.update'), [
            'portfolio_url' => 'https://karim.dev',
            'github_url' => 'http://github.com/karim',
            'linkedin_url' => 'https://www.linkedin.com/in/karim-rahman',
        ])
        ->assertSessionHasNoErrors();

    expect($candidate->candidateProfile->refresh()->only(['portfolio_url', 'github_url', 'linkedin_url']))->toBe([
        'portfolio_url' => 'https://karim.dev',
        'github_url' => 'http://github.com/karim',
        'linkedin_url' => 'https://www.linkedin.com/in/karim-rahman',
    ]);
});

test('a profile link that is not a web address is refused', function (string $field, string $link) {
    $candidate = candidateUser()->fresh();
    $candidate->candidateProfile->update([$field => null]);

    $this->actingAs($candidate)
        ->patch(route('candidate.profile.update'), [$field => $link])
        ->assertSessionHasErrors($field);

    expect($candidate->candidateProfile->refresh()->{$field})->toBeNull();
})->with([
    ['portfolio_url', 'data://text/html,hello'],
    ['portfolio_url', 'ftp://karim.dev/cv.pdf'],
    ['github_url', 'file://localhost/etc/passwd'],
    ['linkedin_url', 'javascript:alert(1)'],
]);
