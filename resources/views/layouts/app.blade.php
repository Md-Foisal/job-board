{{--
    Thin wrapper around the candidate sidebar shell (layouts/app/sidebar.blade.php,
    which already provides the padded <main>). This used to also wrap $slot in
    <flux:main>, but Flux ships a CSS rule keyed off [data-flux-main]
    (*:has(>[data-flux-main]) { display:grid; grid-template-areas: ... }) built
    for its own much larger "Application UI" shell -- any ancestor of a
    [data-flux-main] element gets forced into that grid, which is exactly the
    bug that hit the sidebar shell's own <main> (see sidebar.blade.php's git
    history / 817a698). Nesting <flux:main> here re-introduced the same
    ancestor, one level up, silently: it happened to still render correctly
    because a lone grid child with no named area still gets auto-placed
    somewhere visually plausible -- confirmed via computed-style inspection
    (mainDisplay: "grid", not the intended flex) rather than by eye. Passing
    $slot straight through avoids the [data-flux-main] attribute entirely.
--}}
<x-layouts::app.sidebar :title="$title ?? null">
    {{ $slot }}
</x-layouts::app.sidebar>
