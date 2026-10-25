{{--
    One entry of the simpler profile sections (languages, awards,
    volunteering, publications): an icon, a title with what it belongs to,
    a line of dates or level, an optional note and an optional link.
--}}
@props(['icon', 'title', 'subtitle' => null, 'meta' => null, 'note' => null, 'link' => null])

@php
    $verifiable = filled($link) && preg_match('#^https?://#i', $link) === 1;
@endphp

<li {{ $attributes->class('flex gap-4 px-5 py-4 sm:px-6') }}>
    <x-icon-tile :icon="$icon" size="sm" />

    <div class="min-w-0 flex-1">
        <p class="font-medium text-ink">{{ $title }}</p>
        @if (filled($subtitle))
            <p class="text-sm text-ink-soft">{{ $subtitle }}</p>
        @endif
        @if (filled($meta))
            <p class="mt-0.5 text-sm text-ink-muted">{{ $meta }}</p>
        @endif
        @if (filled($note))
            <p class="mt-2 whitespace-pre-line text-sm text-ink-soft">{{ $note }}</p>
        @endif
        @if ($verifiable)
            <a href="{{ $link }}" target="_blank" rel="noopener noreferrer nofollow" class="mt-1 inline-flex items-center gap-1 text-sm text-sunset-small hover:underline">
                {{ rtrim(preg_replace('#^https?://(www\.)?#i', '', $link), '/') }}
                <flux:icon.arrow-top-right-on-square variant="micro" aria-hidden="true" />
            </a>
        @endif
    </div>

    @isset($actions)
        <div class="shrink-0">{{ $actions }}</div>
    @endisset
</li>
