{{-- The app's day/night switch (components/theme-toggle), for the staff
     panel. Writes the app's setting ("flux.appearance") and tells Filament
     through its own "theme-changed" event, so both sides stay on the same
     theme whichever one it was changed in. --}}
<button
    type="button"
    class="jb-tt"
    x-data="{ dark: document.documentElement.classList.contains('dark') }"
    x-on:click="
        dark = ! dark
        const mode = dark ? 'dark' : 'light'
        try { localStorage.setItem('flux.appearance', mode) } catch (e) {}
        window.dispatchEvent(new CustomEvent('theme-changed', { detail: mode }))
    "
    x-bind:aria-pressed="dark.toString()"
    aria-label="{{ __('Switch to light or dark theme') }}"
>
    <span class="jb-tt-sky jb-tt-day" x-bind:style="{ opacity: dark ? 0 : 1 }">
        <span class="jb-tt-dot" style="left: 34px; top: 7px; width: 17px; height: 9px; opacity: .85"></span>
        <span class="jb-tt-dot" style="left: 41px; top: 4px; width: 11px; height: 7px; opacity: .7"></span>
    </span>
    <span class="jb-tt-sky jb-tt-night" x-bind:style="{ opacity: dark ? 1 : 0 }">
        <span class="jb-tt-dot" style="left: 11px; top: 6px; width: 3px; height: 3px; opacity: .9"></span>
        <span class="jb-tt-dot" style="left: 18px; top: 12px; width: 2px; height: 2px; opacity: .7"></span>
        <span class="jb-tt-dot" style="left: 13px; top: 18px; width: 2px; height: 2px; opacity: .8"></span>
        <span class="jb-tt-dot" style="left: 22px; top: 6px; width: 2px; height: 2px; opacity: .6"></span>
    </span>
    <span class="jb-tt-thumb" x-bind:style="{ transform: dark ? 'translateX(2rem)' : 'translateX(0)' }">
        <svg class="jb-tt-icon" viewBox="0 0 16 16" fill="#f59e0b" aria-hidden="true"
            x-bind:style="dark ? 'transform: scale(0) rotate(-45deg); opacity: 0' : 'transform: scale(1); opacity: 1'">
            <path d="M8 1a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-1.5 0v-1.5A.75.75 0 0 1 8 1ZM10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0ZM12.95 4.11a.75.75 0 1 0-1.06-1.06l-1.062 1.06a.75.75 0 0 0 1.061 1.062l1.06-1.061ZM15 8a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1 0-1.5h1.5A.75.75 0 0 1 15 8ZM11.89 12.95a.75.75 0 0 0 1.06-1.06l-1.06-1.062a.75.75 0 0 0-1.062 1.061l1.061 1.06ZM8 12a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-1.5 0v-1.5A.75.75 0 0 1 8 12ZM5.172 11.89a.75.75 0 0 0-1.061-1.062L3.05 11.89a.75.75 0 1 0 1.06 1.06l1.06-1.06ZM4 8a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1 0-1.5h1.5A.75.75 0 0 1 4 8ZM4.11 5.172A.75.75 0 0 0 5.173 4.11L4.11 3.05a.75.75 0 1 0-1.06 1.06l1.06 1.06Z"/>
        </svg>
        <svg class="jb-tt-icon" viewBox="0 0 16 16" fill="#4f46e5" aria-hidden="true"
            x-bind:style="dark ? 'transform: scale(1); opacity: 1' : 'transform: scale(0) rotate(45deg); opacity: 0'">
            <path d="M14.438 10.148c.19-.425-.321-.787-.748-.601A5.5 5.5 0 0 1 6.453 2.31c.186-.427-.176-.938-.6-.748a6.501 6.501 0 1 0 8.585 8.586Z"/>
        </svg>
    </span>
</button>
