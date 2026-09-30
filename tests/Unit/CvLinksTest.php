<?php

use App\Support\CvLinks;

test('LinkedIn, GitHub and a portfolio are found, written with or without a scheme', function () {
    $text = "Karim Rahman\nlinkedin.com/in/karim-rahman · github.com/karim\nPortfolio: www.karim.dev.";

    expect(CvLinks::from($text))->toBe([
        'linkedin_url' => 'https://www.linkedin.com/in/karim-rahman',
        'github_url' => 'https://github.com/karim',
        'portfolio_url' => 'https://www.karim.dev',
    ]);
});

test('LinkedIn profiles on country subdomains or with tracking query strings are normalised', function () {
    expect(CvLinks::from('https://bd.linkedin.com/in/karim-rahman/?originalSubdomain=bd')['linkedin_url'])
        ->toBe('https://www.linkedin.com/in/karim-rahman');
});

test('pages that are not the candidate\'s own profile are not suggested', function () {
    $text = 'Worked at https://www.linkedin.com/company/acme and contributed to github.com/laravel/framework; see github.com/features';

    expect(CvLinks::from($text))->toBe([
        'linkedin_url' => null,
        'github_url' => null,
        'portfolio_url' => null,
    ]);
});

test('file names and abbreviations are not mistaken for websites', function () {
    expect(CvLinks::from('Built with Node.js and Vue.js, e.g. for karim@acme.com'))->toBe([
        'linkedin_url' => null,
        'github_url' => null,
        'portfolio_url' => null,
    ]);
});

test('the first profile of each kind wins, and later ones are ignored', function () {
    $text = "https://github.com/karim\nhttps://github.com/rahim\nhttps://karim.dev\nhttps://blog.karim.dev";

    expect(CvLinks::from($text))->toMatchArray([
        'github_url' => 'https://github.com/karim',
        'portfolio_url' => 'https://karim.dev',
    ]);
});

test('an address longer than the profile field allows is skipped', function () {
    $long = 'https://karim.dev/'.str_repeat('a', 250);

    expect(CvLinks::from("{$long} https://karim.dev/work")['portfolio_url'])->toBe('https://karim.dev/work');
});
