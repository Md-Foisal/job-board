{{-- The fold button where the app's own sidebars keep it
     (flux:sidebar.collapse in layouts/app/sidebar): at the top of the
     sidebar, with the same icon. Filament's own sits in the top bar and
     is hidden in filament/shell-head. Phones get the drawer instead. --}}
<div class="jb-sidebar-collapse">
    <button
        type="button"
        class="jb-sidebar-collapse-btn"
        aria-controls="fi-main-sidebar"
        x-bind:aria-expanded="$store.sidebar.isOpen"
        x-bind:aria-label="$store.sidebar.isOpen ? @js(__('Collapse sidebar')) : @js(__('Expand sidebar'))"
        x-bind:title="$store.sidebar.isOpen ? @js(__('Collapse sidebar')) : @js(__('Expand sidebar'))"
        x-on:click="$store.sidebar.isOpen ? $store.sidebar.close() : $store.sidebar.open()"
    >
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d="M7.5 3.75V16.25M3.4375 16.25H16.5625C17.08 16.25 17.5 15.83 17.5 15.3125V4.6875C17.5 4.17 17.08 3.75 16.5625 3.75H3.4375C2.92 3.75 2.5 4.17 2.5 4.6875V15.3125C2.5 15.83 2.92 16.25 3.4375 16.25Z" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </button>
</div>
