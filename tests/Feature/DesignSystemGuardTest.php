<?php

use Symfony\Component\Finder\Finder;

/*
 * The design system is the tokens in resources/css/app.css and the
 * components in resources/views/components. A page that copies a colour
 * or a block by hand instead stops following the system: change the card
 * or the muted grey once, and that page keeps the old look. These checks
 * fail when a view goes back to the hand-made version, and name the
 * shared piece to use instead.
 */

/**
 * Every Blade view, keyed by its path under resources/views.
 *
 * @return array<string, list<string>>
 */
function bladeViewLines(): array
{
    $views = [];

    foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
        $views[str_replace('\\', '/', $file->getRelativePathname())] = preg_split('/\R/', $file->getContents());
    }

    return $views;
}

/*
 * The few places where a raw value is the point, each with its reason.
 * Matched on the file and a piece of the offending line, so a second,
 * unexplained use in the same file still fails.
 */
const DESIGN_SYSTEM_EXCEPTIONS = [
    // The CV preview is a printed page: white whichever theme the app is in.
    ['pages/candidate/⚡cv-builder.blade.php', 'bg-white shadow-sm'],
    // A QR code needs a light background to scan.
    ['pages/settings/⚡two-factor-setup-modal.blade.php', 'class="bg-white p-3 rounded"'],
    // The large letter printed on a company's default cover, not its logo.
    ['companies/show.blade.php', 'Str::of($company->name)->substr(0, 1)'],
];

dataset('hand-made patterns', [
    'neutral palette colours' => [
        '/(?<![\w-])(?:[a-z0-9-]+:)*(?:text|bg|border|divide|ring|outline|placeholder|from|via|to|fill|stroke|decoration)-(?:zinc|stone|neutral|gray|slate)-\d/',
        'Use the role tokens: text-ink, text-ink-soft, text-ink-muted, bg-canvas, bg-surface, border-line, border-line-strong.',
        [],
    ],
    'plain white surfaces' => [
        '/(?<![\w-])(?:[a-z0-9-]+:)*bg-white(?![\w\/-])/',
        'Use bg-canvas, which follows the theme.',
        [],
    ],
    'flat brand text' => [
        '/(?<![\w-])(?:[a-z0-9-]+:)*text-brand-\d/',
        'Coloured text is the Sunset gradient: text-sunset at 24px and up, text-sunset-small below.',
        [],
    ],
    'solid brand surfaces and lines' => [
        '/(?<![\w-])(?:[a-z0-9-]+:)*(?:bg|from|via|to|border|ring|fill|stroke)-brand-(?:[2-8]\d\d|900)\b/',
        'A coloured surface is the Sunset fill: bg-sunset, or .btn-sunset for the main action on a page.',
        [],
    ],
    'raw palette colours' => [
        '/(?<![\w-])(?:[a-z0-9-]+:)*(?:text|bg|border|divide|ring|fill|stroke|from|via|to)-(?:red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-\d/',
        'Status colours are the success, warning and danger tokens; accents are the brand tint and the Sunset gradient.',
        ['components/company-logo.blade.php'],
    ],
    'badges outside the status colours' => [
        '/<flux:badge\b[^>]*\scolor="(?!(?:green|amber|red|zinc)")[^"]*"/',
        'A badge colour is a status: green, amber or red, and zinc when it says nothing good or bad.',
        [],
    ],
    'hand-drawn empty states' => [
        '/(?<![\w-])border-dashed(?![\w-])/',
        'An empty list or section is <x-empty-state>.',
        ['components/empty-state.blade.php', 'components/chip.blade.php'],
    ],
    'hand-drawn letter logos' => [
        '/->substr\(0,\s*1\)/',
        "A company's logo or initial is <x-company-logo>; a person's is flux:avatar or User::initials().",
        ['components/company-logo.blade.php'],
    ],
]);

test('views use the shared tokens and components instead of copies', function (string $pattern, string $fix, array $definedIn) {
    $offenders = [];

    foreach (bladeViewLines() as $path => $lines) {
        if (in_array($path, $definedIn, true)) {
            continue;
        }

        foreach ($lines as $index => $line) {
            if (! preg_match($pattern, $line)) {
                continue;
            }

            $excepted = collect(DESIGN_SYSTEM_EXCEPTIONS)
                ->contains(fn (array $exception) => $exception[0] === $path && str_contains($line, $exception[1]));

            if (! $excepted) {
                $offenders[] = $path.':'.($index + 1);
            }
        }
    }

    expect($offenders)->toBe([], $fix);
})->with('hand-made patterns');

test('a bordered, rounded block on the page colour is an x-card', function () {
    $offenders = [];

    foreach (bladeViewLines() as $path => $lines) {
        if ($path === 'components/card.blade.php') {
            continue;
        }

        foreach ($lines as $index => $line) {
            preg_match_all('/\bclass="([^"]*)"/', $line, $matches);

            foreach ($matches[1] as $value) {
                $classes = preg_split('/\s+/', trim($value));

                $isCard = in_array('border', $classes, true)
                    && in_array('border-line', $classes, true)
                    && array_intersect($classes, ['rounded-lg', 'rounded-xl', 'rounded-2xl', 'rounded-3xl', 'rounded-card']) !== []
                    && (in_array('bg-canvas', $classes, true) || preg_grep('/^bg-/', $classes) === [])
                    // Menus, popovers and search boxes float above the page
                    // and are not cards.
                    && preg_grep('/^(absolute|shadow|z-)/', $classes) === [];

                if ($isCard) {
                    $offenders[] = $path.':'.($index + 1);
                }
            }
        }
    }

    expect($offenders)->toBe([], 'Use <x-card>, with padding="none" when the content reaches its edges.');
});
