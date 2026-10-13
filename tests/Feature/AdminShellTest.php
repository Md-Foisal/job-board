<?php

use App\Enums\StaffRole;
use Illuminate\Support\Facades\Storage;

test('the staff panel carries the app wordmark, its theme switch and a way back to the site', function () {
    $this->actingAs(staffWithTwoFactor())
        ->get('/admin')
        ->assertOk()
        ->assertSee('jb-brand', false)
        ->assertSee('<span>HearBack</span>', false)
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

test('the panel draws initials itself instead of sending staff names to an avatar service', function () {
    $html = $this->actingAs(staffWithTwoFactor())->get('/admin')->getContent();

    expect($html)->not->toContain('ui-avatars.com')
        ->and($html)->toContain('data:image/svg+xml;base64');
});

test('the panel shows a staff member\'s own photo when they have one', function () {
    $staff = staffWithTwoFactor();
    $staff->forceFill(['avatar' => 'avatars/priya.jpg'])->save();

    $this->actingAs($staff)
        ->get('/admin')
        ->assertSee(Storage::url('avatars/priya.jpg'), false);
});

test('the panel carries the gradient the app paints its coloured icons with', function () {
    $this->actingAs(staffWithTwoFactor())
        ->get('/admin')
        ->assertSee('id="sunset-icon"', false);
});

test('the people list calls a person\'s role a role', function () {
    $this->actingAs(staffWithTwoFactor(StaffRole::SuperAdmin))
        ->get('/admin/users')
        ->assertOk()
        ->assertSee('Role')
        ->assertDontSee('Standing');
});

test('a panel page reached through wire:navigate reloads itself in full', function () {
    $this->actingAs(staffWithTwoFactor())
        ->get('/admin')
        ->assertSee("if (document.readyState !== 'loading')", false)
        ->assertSee('window.location.replace', false);
});

test('the site\'s Dashboard link takes staff to the panel as a full page load', function () {
    $html = $this->actingAs(staffWithTwoFactor())->get(route('home'))->assertOk()->getContent();
    $panel = preg_quote(filament()->getPanel('admin')->getUrl(), '/');

    preg_match('/<a href="'.$panel.'"[^>]*>\s*Dashboard\s*<\/a>/', $html, $link);

    expect($link)->not->toBeEmpty()
        ->and($link[0])->not->toContain('wire:navigate');
});

test('the panel sidebar folds to icons on desktop, from a button in the sidebar as in the app', function () {
    $this->actingAs(staffWithTwoFactor())
        ->get('/admin')
        ->assertOk()
        ->assertSee('class="jb-sidebar-collapse-btn"', false)
        ->assertSee('.fi-topbar-collapse-sidebar-btn-ctn { display: none !important; }', false);

    expect(filament()->getPanel('admin')->isSidebarCollapsibleOnDesktop())->toBeTrue();
});

test('the user menu leads to the account settings every user has', function () {
    $this->actingAs(staffWithTwoFactor())
        ->get('/admin')
        ->assertSee('Account settings')
        ->assertSee(route('profile.edit'), false);
});
