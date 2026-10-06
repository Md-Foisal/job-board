{{--
    Paint servers shared by the page's icons. An icon cannot hold a
    gradient of its own (Flux renders the <svg>), so .icon-sunset points
    its fill or stroke at this one. The stops read the Sunset text set,
    which already has a dark value, so the icons follow the theme.
--}}
<svg width="0" height="0" class="absolute" aria-hidden="true" focusable="false">
    <defs>
        <linearGradient id="sunset-icon" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" style="stop-color: var(--color-sunset-text-1)" />
            <stop offset="0.55" style="stop-color: var(--color-sunset-text-2)" />
            <stop offset="1" style="stop-color: var(--color-sunset-text-3)" />
        </linearGradient>
    </defs>
</svg>
