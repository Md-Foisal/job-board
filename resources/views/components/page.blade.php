{{--
    The column every workspace page sits in. Two widths only, so moving
    through the sidebar does not make the page jump: "wide" for
    dashboards, lists and detail pages, "narrow" for a page that is one
    form or a short choice. Sections stack with the same gap on every
    page.
--}}
@props(['width' => 'wide'])

<div {{ $attributes->class([
    'mx-auto flex w-full flex-col gap-6',
    'max-w-6xl' => $width === 'wide',
    'max-w-3xl' => $width === 'narrow',
]) }}>
    {{ $slot }}
</div>
