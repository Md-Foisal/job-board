{{--
    One entry on an application timeline. The dot,
    the label and the timestamp formatting were written twice on that
    page -- once for the application itself, once inside the event loop
    -- which meant the date format lived in two places.

    Expects to sit inside an <ol> that carries the vertical rule
    (border-s), because the dot is absolutely positioned against it.

    @param string $label
    @param \Illuminate\Support\Carbon $at
    @param bool $highlight  true for the entry that started it all.
--}}
@props(['label', 'at', 'highlight' => false])

<li class="ms-6 pb-8 last:pb-0">
    <span class="absolute -start-1.5 mt-1.5 size-3 rounded-full {{ $highlight ? 'bg-brand-600 dark:bg-brand-500' : 'bg-zinc-300 dark:bg-zinc-700' }}"></span>

    <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $label }}</p>

    <time class="text-sm text-zinc-500 dark:text-zinc-500" datetime="{{ $at->toIso8601String() }}">
        {{ $at->format('j M Y, g:i a') }}
    </time>
</li>
