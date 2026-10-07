{{--
    One project on a candidate's profile: its name, when, what it is,
    and where it can be seen running and where its code is -- the two
    links a software CV is read for.
--}}
@props(['project'])

@php
    $links = collect([
        ['url' => $project->url, 'label' => __('Live'), 'icon' => 'globe-alt'],
        ['url' => $project->source_url, 'label' => __('Code'), 'icon' => 'code-bracket'],
    ])->filter(fn (array $link) => filled($link['url']) && preg_match('#^https?://#i', $link['url']) === 1);
@endphp

<li {{ $attributes->class('flex gap-4 px-5 py-4 sm:px-6') }}>
    <x-icon-tile icon="rocket-launch" size="sm" />

    <div class="min-w-0 flex-1">
        <p class="font-medium text-ink">{{ $project->name }}</p>
        @if ($project->start_date)
            <p class="mt-0.5 text-sm text-ink-muted">
                {{ $project->start_date->format(\App\Support\DateFormat::MONTH) }} &ndash; {{ $project->end_date?->format(\App\Support\DateFormat::MONTH) ?? __('Present') }}
            </p>
        @endif

        @if ($project->description)
            <div class="prose-content mt-2 text-sm">{!! $project->description !!}</div>
        @endif

        @if ($links->isNotEmpty())
            <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                @foreach ($links as $link)
                    <li class="flex min-w-0 items-center gap-1.5">
                        <flux:icon :name="$link['icon']" variant="micro" class="shrink-0 text-ink-muted" aria-hidden="true" />
                        <span class="sr-only">{{ $link['label'] }}:</span>
                        <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer nofollow" class="truncate text-sunset-small hover:underline">
                            {{ rtrim(preg_replace('#^https?://(www\.)?#i', '', $link['url']), '/') }}
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @isset($actions)
        <div class="shrink-0">{{ $actions }}</div>
    @endisset
</li>
