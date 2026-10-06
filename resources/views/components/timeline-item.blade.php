{{--
    One entry on an application timeline. The dot,
    the label and the timestamp formatting were written twice on that
    page -- once for the application itself, once inside the event loop
    -- which meant the date format lived in two places.

    Expects to sit inside an <ol> that carries the vertical rule
    (border-s), because the dot is absolutely positioned against it.

    @param string $label
    @param \Carbon\CarbonInterface $at  stored in UTC; shown in the reader's zone.
    @param bool $highlight  true for the entry that started it all.
--}}
@props(['label', 'at', 'highlight' => false])

@php($local = \App\Support\LocalTime::of($at))

<li class="ms-6 pb-8 last:pb-0">
    <span class="absolute -start-1.5 mt-1.5 size-3 rounded-full {{ $highlight ? 'bg-sunset' : 'bg-line-strong' }}"></span>

    <p class="font-medium text-ink">{{ $label }}</p>

    <time class="text-sm text-ink-muted" datetime="{{ $local->toIso8601String() }}">
        {{ $local->format(\App\Support\DateFormat::MOMENT) }}
    </time>
</li>
