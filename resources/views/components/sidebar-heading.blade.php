{{--
    A section label inside a sidebar. Not flux:sidebar.group: Flux hides a
    whole group, items included, when the sidebar is collapsed to icons,
    so the items sit directly in the nav and only this label gives way to
    a hairline when the sidebar is narrow.
--}}
<div {{ $attributes->class('px-3 pt-5 pb-1.5 text-meta font-medium text-ink-muted first:pt-1 in-data-flux-sidebar-collapsed-desktop:hidden') }}>{{ $slot }}</div>
<div class="mx-2 my-2 hidden h-px bg-line in-data-flux-sidebar-collapsed-desktop:block" aria-hidden="true"></div>
