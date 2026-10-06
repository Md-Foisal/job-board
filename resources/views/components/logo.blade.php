{{--
    The product's mark and wordmark. The mark is a ring three-quarters closed,
    the shape of a match score, drawn in the Sunset fill on an ink tile;
    the dot in the middle is cut out of the tile rather than painted, so
    it shows whatever is behind it and needs no colour of its own. Used
    in every top bar, on the auth pages, the error pages and the staff
    panel, which has no Tailwind build of its own -- there, the
    "jb-brand" rules in filament/shell-head take the place of the
    classes below. Each copy gets its own ids, since several can share a
    page.
--}}
@props(['size' => 'md'])

@php
    $id = 'jb-logo-'.\Illuminate\Support\Str::random(8);
    $mark = $size === 'lg' ? 32 : 26;
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-2.5 font-bold tracking-[-0.03em] text-ink', 'text-2xl' => $size === 'lg', 'text-lg' => $size !== 'lg']) }}>
    <svg width="{{ $mark }}" height="{{ $mark }}" viewBox="0 0 26 26" class="shrink-0" aria-hidden="true">
        <defs>
            <linearGradient id="{{ $id }}-ring" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" style="stop-color: var(--color-sunset-1, #c2410c)" />
                <stop offset="0.55" style="stop-color: var(--color-sunset-2, #db2777)" />
                <stop offset="1" style="stop-color: var(--color-sunset-3, #7c3aed)" />
            </linearGradient>
            <mask id="{{ $id }}-hole">
                <rect width="26" height="26" fill="white" />
                <circle cx="13" cy="13" r="2.2" fill="black" />
            </mask>
        </defs>
        <rect width="26" height="26" rx="7" fill="currentColor" mask="url(#{{ $id }}-hole)" />
        <circle cx="13" cy="13" r="6.5" fill="none" stroke="url(#{{ $id }}-ring)" stroke-width="2.5" stroke-linecap="round" stroke-dasharray="30 41" transform="rotate(-90 13 13)" />
    </svg>
    <span>{{ config('app.name') }}</span>
</span>
