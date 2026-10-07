{{--
    Shared frame for the static guest pages
    (About / Privacy / Terms). The heading doubles as the browser-tab
    title, so a new static page cannot accidentally ship without one.

    A page with five or more sections gets an "On this page" list,
    built from the section headings already in the slot, so the list
    cannot fall out of step with the sections it points to.

    @param string $heading
    @param string|null $lead  One sentence under the heading.
--}}
@props(['heading', 'lead' => null])

@php
    $body = (string) $slot;
    preg_match_all('/<h2 id="([^"]+)"[^>]*>(.*?)<\/h2>/s', $body, $sections, PREG_SET_ORDER);
@endphp

<x-layouts::guest :title="$heading">
    <header class="relative overflow-hidden border-b border-line">
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,var(--color-line-strong)_1px,transparent_0)] [background-size:22px_22px] [mask-image:radial-gradient(ellipse_at_top_left,black_20%,transparent_70%)]"></div>

        <div class="relative mx-auto max-w-3xl px-6 py-12 sm:py-16">
            <x-breadcrumb :items="[['label' => $heading]]" />
            <h1 class="mt-4 text-title text-ink sm:text-display">{{ $heading }}</h1>
            @if (filled($lead))
                <p class="mt-4 max-w-2xl text-body text-ink-muted sm:text-lg">{{ $lead }}</p>
            @endif
        </div>
    </header>

    <div class="mx-auto max-w-3xl px-6 py-12">
        @if (count($sections) >= 5)
            <nav aria-labelledby="on-this-page" class="mb-12 border-l-2 border-line ps-4">
                <h2 id="on-this-page" class="text-meta font-semibold uppercase tracking-wide text-ink-muted">{{ __('On this page') }}</h2>
                <ul class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    @foreach ($sections as [, $id, $title])
                        <li><a href="#{{ $id }}" class="text-ink-soft hover:text-sunset-small hover:underline">{!! $title !!}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif

        <article class="space-y-10 text-body text-ink-soft">
            {!! $body !!}
        </article>
    </div>
</x-layouts::guest>
