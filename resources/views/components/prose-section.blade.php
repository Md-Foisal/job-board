{{--
    One titled section inside a static page. The heading's id is what the
    page's "On this page" list links to.

    @param string $heading
--}}
@props(['heading'])

<section class="space-y-3">
    <h2 id="{{ \Illuminate\Support\Str::slug($heading) }}" class="text-heading text-ink">{{ $heading }}</h2>
    {{ $slot }}
</section>
