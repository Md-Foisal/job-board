{{--
    One titled section inside a static page. The heading style was
    repeated nine times across About/Privacy/Terms before this existed,
    so changing it meant nine edits and one of them getting missed.

    @param string $heading
--}}
@props(['heading'])

<section class="space-y-3">
    <h2 class="font-display text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $heading }}</h2>
    {{ $slot }}
</section>
