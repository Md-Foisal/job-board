{{--
    Makes the staff panel look and behave like the rest of the product
    (partials/navbar, layouts/app/sidebar), without a separate Filament
    theme build: the wordmark, the same surfaces for the top bar, sidebar
    and page, and one light/dark setting shared with the app.

    Filament keeps its own theme choice under localStorage "theme"; the app
    (Flux) keeps it under "flux.appearance". Left alone the two drift -- an
    app set to dark could open a light panel. So the app's choice is copied
    into Filament's key here, before Filament's own script reads it (this
    renders at STYLES_AFTER, above that script), and the panel's toggle
    (filament/theme-toggle) writes both.
--}}
@include('partials.timezone-cookie')

<script>
    (() => {
        try {
            localStorage.setItem('theme', localStorage.getItem('flux.appearance') || 'system')
        } catch (e) {}
    })()
</script>

<script>
    // The Sunset gradient for a chart (widgets/PlatformActivityChart), left
    // to right across the plot area, made per draw since the area is only
    // known once laid out and the stops change with the theme.
    window.jbSunset = (chart, alpha) => {
        const style = getComputedStyle(document.documentElement)
        const stops = ['--color-sunset-ink-1', '--color-sunset-ink-2', '--color-sunset-ink-3']
            .map((name) => style.getPropertyValue(name).trim())
        const { ctx, chartArea } = chart
        const tint = (colour) => alpha === 1 ? colour : colour + Math.round(alpha * 255).toString(16).padStart(2, '0')

        if (! chartArea) {
            return tint(stops[1])
        }

        const gradient = ctx.createLinearGradient(chartArea.left, 0, chartArea.right, 0)
        gradient.addColorStop(0, tint(stops[0]))
        gradient.addColorStop(0.55, tint(stops[1]))
        gradient.addColorStop(1, tint(stops[2]))

        return gradient
    }
</script>

<style>
    /* The logo (components/logo): the app styles it with Tailwind
       classes the panel's stylesheet does not have. */
    .jb-brand {
        display: inline-flex;
        align-items: center;
        gap: 0.625rem;
        font-size: 1.125rem;
        font-weight: 700;
        letter-spacing: -0.03em;
        color: var(--gray-950);
    }
    .dark .jb-brand { color: #ededef; }

    /* Page, top bar and sidebar, as in the app: the bar sits on the page
       colour with a hairline under it; the sidebar is its own panel. */
    .fi-body { background-color: #fff; }
    .dark .fi-body { background-color: var(--gray-950); }

    .fi-topbar {
        background-color: #fff;
        border-bottom: 1px solid var(--gray-200);
        box-shadow: none;
    }
    .dark .fi-topbar {
        background-color: var(--gray-950);
        border-bottom-color: var(--gray-800);
    }

    .fi-sidebar {
        background-color: var(--gray-50);
        border-inline-end: 1px solid var(--gray-200);
    }
    .dark .fi-sidebar {
        background-color: var(--gray-900);
        border-inline-end-color: var(--gray-800);
    }

    /*
     * The colour rule of the app (resources/css/app.css): anything coloured
     * that is not a status carries the Sunset gradient, and the rest is
     * ink. Filament paints its "primary" in one flat orange, so each place
     * it shows is mapped here. The variables are the app's; partials/
     * svg-defs (rendered at the top of the body) reads them for icons.
     */
    :root {
        --color-sunset-1: #c2410c;
        --color-sunset-2: #db2777;
        --color-sunset-3: #7c3aed;
        --color-sunset-text-1: #c2410c;
        --color-sunset-text-2: #be185d;
        --color-sunset-text-3: #7c3aed;
        --color-sunset-ink-1: #ea580c;
        --color-sunset-ink-2: #db2777;
        --color-sunset-ink-3: #7c3aed;
        --jb-ink: #0a0a0a;
    }
    .dark {
        --color-sunset-text-1: #fbbf24;
        --color-sunset-text-2: #f472b6;
        --color-sunset-text-3: #a78bfa;
        --color-sunset-ink-1: #fbbf24;
        --color-sunset-ink-2: #f472b6;
        --color-sunset-ink-3: #a78bfa;
        --jb-ink: #ededef;
    }

    /* The current page in the menu, and the current tab: ink label, the
       icon in the gradient -- as in the app's sidebar. */
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-sidebar-item-label,
    .fi-tabs-item.fi-active .fi-tabs-item-label {
        color: var(--jb-ink);
    }
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-icon,
    .fi-tabs-item.fi-active > .fi-icon {
        color: var(--jb-ink);
    }
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > svg.fi-icon[fill='none'] { stroke: url(#sunset-icon); }
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > svg.fi-icon:not([fill='none']) { fill: url(#sunset-icon); }

    /* A filled primary button is a page's main action (Add skill, the
       confirm button of a dialog): the Sunset fill with white text, the
       app's .btn-sunset. Every stop holds 4.5:1 against white. */
    .fi-btn.fi-color-primary:not(.fi-outlined) {
        color: #fff;
        background-color: var(--color-sunset-1);
        background-image: linear-gradient(120deg, var(--color-sunset-1) 0%, var(--color-sunset-2) 55%, var(--color-sunset-3) 100%);
        transition: transform 150ms, box-shadow 150ms;
    }
    .fi-btn.fi-color-primary:not(.fi-outlined):hover {
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 6px 20px -6px rgb(219 39 119 / 0.45);
    }
    .fi-btn.fi-color-primary:not(.fi-outlined) .fi-icon { color: #fff; }

    /* A primary link (Rename, Merge into…) reads as the app's links do:
       gradient text from the darker text set, the icon to match. */
    .fi-link.fi-color-primary {
        background-image: linear-gradient(120deg, var(--color-sunset-text-1) 0%, var(--color-sunset-text-2) 55%, var(--color-sunset-text-3) 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        text-decoration-color: var(--color-sunset-text-2);
    }
    .fi-link.fi-color-primary > svg.fi-icon[fill='none'] { stroke: url(#sunset-icon); }
    .fi-link.fi-color-primary > svg.fi-icon:not([fill='none']) { fill: url(#sunset-icon); }

    /* A badge left at Filament's default colour is a plain fact (a role,
       a topic, a flag's name), not a state: grey, as in the app. Badges
       that mean something set their own colour and are untouched. */
    .fi-badge.fi-color-primary {
        --color-50: var(--gray-50);
        --color-100: var(--gray-100);
        --color-200: var(--gray-200);
        --color-300: var(--gray-300);
        --color-400: var(--gray-400);
        --color-500: var(--gray-500);
        --color-600: var(--gray-600);
        --color-700: var(--gray-700);
        --color-800: var(--gray-800);
        --color-900: var(--gray-900);
        --color-950: var(--gray-950);
    }

    /* The current page number, in ink rather than orange. */
    .fi-pagination-item.fi-active .fi-pagination-item-label { color: var(--jb-ink); }

    /* The light/dark switch -- the app's components/theme-toggle, in
       plain CSS for the same reason. */
    .jb-tt {
        display: inline-flex; align-items: center; justify-content: center;
        width: 2.25rem; height: 2.25rem; margin-inline-end: 0.75rem; flex-shrink: 0;
        border-radius: 0.625rem; border: 1px solid var(--gray-200);
        background-color: var(--gray-50); color: var(--gray-950);
        cursor: pointer; transition: background-color 150ms;
    }
    .jb-tt:hover { background-color: var(--gray-100); }
    .jb-tt:focus-visible { outline: 2px solid var(--gray-950); outline-offset: 2px; }
    .jb-tt svg { width: 18px; height: 18px; }
    .dark .jb-tt { border-color: var(--gray-800); background-color: var(--gray-900); color: #ededef; }
    .dark .jb-tt:hover { background-color: var(--gray-800); }
    .dark .jb-tt:focus-visible { outline-color: #ededef; }
</style>
