{{--
    Shared frame for the static guest pages of claude/14 route ১০
    (About / Privacy / Terms). The heading doubles as the browser-tab
    title, so a new static page cannot accidentally ship without one.

    @param string $heading
--}}
@props(['heading'])

<x-layouts::guest :title="$heading">
    <article class="mx-auto max-w-2xl px-6 py-12">
        <h1 class="font-display text-3xl font-bold text-zinc-900 dark:text-zinc-50">{{ $heading }}</h1>

        <div class="mt-6 space-y-6 text-zinc-700 dark:text-zinc-300">
            {{ $slot }}
        </div>
    </article>
</x-layouts::guest>
