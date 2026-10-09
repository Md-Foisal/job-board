<?php

use App\Models\Company;
use App\Models\User;
use App\Notifications\CompanyDocumentsRequested;
use Illuminate\Mail\Markdown;

beforeEach(function () {
    $this->mail = (new CompanyDocumentsRequested(Company::factory()->create(['name' => 'Mapleway']), 'A trade licence.'))
        ->toMail(User::factory()->create());
});

test('every email carries the product\'s mark and name, not Laravel\'s', function () {
    $html = (string) $this->mail->render();

    expect($html)->toContain(asset('images/mail-logo.png'))
        ->and($html)->toContain('>'.config('app.name').'</span>')
        ->and($html)->not->toContain('laravel.com/img');
});

test('the button carries the Sunset fill, with a solid colour for clients that cannot draw a gradient', function () {
    $html = (string) $this->mail->render();

    expect($html)->toContain('bgcolor="#c2410c"')
        // Outlook drops a link's padding; the cell gives it back there.
        ->and($html)->toContain('mso-padding-alt: 12px 22px')
        ->and($html)->toContain('linear-gradient(120deg, #c2410c 0%, #db2777 55%, #7c3aed 100%)');
});

test('emails are signed by the team and point to the privacy notice and the contact page', function () {
    $html = (string) $this->mail->render();

    expect($html)->toContain('The '.config('app.name').' team')
        ->and($html)->not->toContain('Regards,')
        ->and($html)->toContain('If the button does not work')
        ->and($html)->toContain(route('privacy'))
        ->and($html)->toContain(route('contact'));
});

test('the footer is written in a grey that can be read on the page colour', function () {
    $html = (string) $this->mail->render();

    // Laravel's #a1a1aa reads at 2.5:1 on #fafafa; the app's muted ink at 6:1.
    expect($html)->toContain('color: #5f5f66; font-size: 12px')
        ->and($html)->not->toContain('color: #a1a1aa; font-size: 12px');
});

test('readers in dark mode get the app\'s dark colours where their mail app allows it', function () {
    $html = (string) $this->mail->render();

    expect($html)->toContain('<meta name="color-scheme" content="light dark">')
        ->and($html)->toContain('@media (prefers-color-scheme: dark)');
});

test('the plain-text version carries the same footer links', function () {
    $text = (string) app(Markdown::class)->renderText($this->mail->markdown, $this->mail->data());

    expect($text)->toContain(route('privacy'))
        ->and($text)->toContain(route('contact'))
        ->and($text)->not->toContain('All rights reserved');
});

test('emails greet a person the way the dashboard does', function (string $name, string $greeting) {
    $mail = (new CompanyDocumentsRequested(Company::factory()->create(), 'A trade licence.'))
        ->toMail(User::factory()->create(['name' => $name]));

    expect($mail->greeting)->toBe($greeting);
})->with([
    ['Hannah Lewis', 'Hello Hannah,'],
    ['Md. Foisal', 'Hello Md. Foisal,'],
]);
