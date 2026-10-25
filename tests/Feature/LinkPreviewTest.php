<?php

use App\Models\Company;
use App\Models\JobPosting;

/*
 * What a link to a page shows when it is shared on LinkedIn, Facebook,
 * X or in a chat: Open Graph and Twitter card tags in partials/head.
 */

test('the home page carries a full link preview with an absolute image', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)
        ->toContain('<meta property="og:title" content="'.config('app.name').'">')
        ->toContain('<meta property="og:image" content="'.asset('images/og-image.jpg').'">')
        ->toContain('<meta name="twitter:card" content="summary_large_image">')
        ->toContain('<meta name="description" content="Find work that fits your skills.');

    expect(asset('images/og-image.jpg'))->toStartWith('http');
    expect(public_path('images/og-image.jpg'))->toBeFile();
    [$width, $height] = getimagesize(public_path('images/og-image.jpg'));
    expect([$width, $height])->toBe([1200, 630]);
});

test('a public job shares its own title and a plain line from its description', function () {
    $company = Company::factory()->create(['name' => 'Acme Ltd']);
    $job = JobPosting::factory()->create([
        'company_id' => $company->id,
        'title' => 'Senior Laravel Developer',
        'description' => '<p>Build <strong>billing</strong> &amp; reports.</p><ul><li>Laravel</li></ul>',
    ]);

    $this->get(route('jobs.show', $job))
        ->assertOk()
        ->assertSee('<meta property="og:title" content="Senior Laravel Developer at Acme Ltd">', false)
        ->assertSee('<meta property="og:description" content="Build billing &amp; reports. Laravel">', false)
        ->assertSee('<meta property="og:url" content="'.route('jobs.show', $job).'">', false);
});

test('a draft a company member previews keeps the site line, not its own text', function () {
    $job = JobPosting::factory()->draft()->create(['description' => '<p>Secret draft wording</p>']);

    $this->actingAs(employerUser($job->company))
        ->get(route('jobs.show', $job))
        ->assertOk()
        ->assertDontSee('content="Secret draft wording', false)
        ->assertSee('<meta property="og:description" content="Find work that fits your skills.', false);
});

test('a company page shares its about text as its line', function () {
    $company = Company::factory()->create([
        'name' => 'Acme Ltd',
        'description' => '<p>We make accounting software for small shops.</p>',
    ]);

    $this->get(route('companies.show', $company))
        ->assertOk()
        ->assertSee('<meta property="og:title" content="Acme Ltd">', false)
        ->assertSee('<meta property="og:description" content="We make accounting software for small shops.">', false);
});
