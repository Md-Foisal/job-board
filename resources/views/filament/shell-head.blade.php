{{--
    Makes the staff panel look and behave like the rest of the product
    (partials/navbar, layouts/app/sidebar), without a separate Filament
    theme build: the display font for the wordmark, the same surfaces for
    the top bar, sidebar and page, and one light/dark setting shared with
    the app.

    Filament keeps its own theme choice under localStorage "theme"; the app
    (Flux) keeps it under "flux.appearance". Left alone the two drift -- an
    app set to dark could open a light panel. So the app's choice is copied
    into Filament's key here, before Filament's own script reads it (this
    renders at STYLES_AFTER, above that script), and the panel's toggle
    (filament/theme-toggle) writes both.
--}}
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=bricolage-grotesque:700" rel="stylesheet" />

<script>
    (() => {
        try {
            localStorage.setItem('theme', localStorage.getItem('flux.appearance') || 'system')
        } catch (e) {}
    })()
</script>

<style>
    .jb-brand {
        font-family: 'Bricolage Grotesque', var(--font-family), sans-serif;
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--primary-700);
    }
    .dark .jb-brand { color: var(--primary-400); }

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

    /* The day/night switch -- the app's components/theme-toggle, in plain
       CSS because the panel does not load the app's stylesheet. */
    .jb-tt {
        position: relative; display: inline-flex; align-items: center;
        width: 4rem; height: 2rem; padding: 0.25rem; margin-inline-end: 0.75rem;
        border-radius: 9999px; box-shadow: inset 0 2px 4px rgb(0 0 0 / 0.15);
        cursor: pointer; flex-shrink: 0; border: 0;
    }
    .jb-tt:focus-visible { outline: 2px solid var(--primary-400); outline-offset: 2px; }
    .jb-tt-sky { position: absolute; inset: 0; border-radius: 9999px; overflow: hidden; transition: opacity 500ms ease-in-out; }
    .jb-tt-day { background: linear-gradient(120deg, #8ec9ec 0%, #cdeaf9 55%, #eef8fd 100%); }
    .jb-tt-night { background: linear-gradient(120deg, #0b1229 0%, #182552 60%, #22306b 100%); }
    .jb-tt-dot { position: absolute; border-radius: 9999px; background: #fff; }
    .jb-tt-thumb {
        position: relative; z-index: 1; display: flex; align-items: center; justify-content: center;
        width: 1.5rem; height: 1.5rem; border-radius: 9999px; background: #fff;
        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        transition: transform 500ms ease-out;
    }
    .jb-tt-icon { position: absolute; width: 1rem; height: 1rem; transition: all 300ms; }
</style>
