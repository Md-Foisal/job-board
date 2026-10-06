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

    /* Same rounded-square avatar as the app's account menu. */
    .fi-user-menu .fi-avatar.fi-circular { border-radius: 0.5rem; }

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
