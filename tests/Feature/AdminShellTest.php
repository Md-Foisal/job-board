<?php

test('the staff panel carries the app wordmark, its theme switch and a way back to the site', function () {
    $this->actingAs(staffWithTwoFactor())
        ->get('/admin')
        ->assertOk()
        ->assertSee('<span class="jb-brand">JobBoard</span>', false)
        ->assertSee('class="jb-tt"', false)
        ->assertSee('View site')
        ->assertSee(route('home'), false)
        // Filament's own three-way switcher would disagree with the app's.
        ->assertDontSee('fi-theme-switcher', false);
});

test('the panel takes the app\'s light/dark choice before Filament reads its own', function () {
    $html = $this->actingAs(staffWithTwoFactor())->get('/admin')->getContent();

    $sync = strpos($html, "localStorage.getItem('flux.appearance')");
    $filamentRead = strpos($html, 'const loadDarkMode');

    expect($sync)->not->toBeFalse()
        ->and($filamentRead)->not->toBeFalse()
        ->and($sync)->toBeLessThan($filamentRead);
});
