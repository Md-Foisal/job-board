{{--
    A two-state day/night theme switch. Reads/writes Flux's own reactive
    appearance store directly ($flux.dark / $flux.appearance -- the same
    magic the Settings > Appearance page's flux:radio.group binds to via
    x-model="$flux.appearance"), so this stays interchangeable with that
    page; this component only adds a hand-drawn sky face on top, and only
    ever sets an explicit "light" or "dark" (the three-way "system" option
    still lives on the settings page for anyone who wants it).

    Note this is NOT the same as the window.Flux.applyAppearance() helper
    that @fluxAppearance (partials/head.blade.php) defines for the earliest
    possible paint, before Alpine/Flux's JS has loaded -- once Flux's own
    JS boots (@fluxScripts), it replaces window.Flux with its own reactive
    object that doesn't expose applyAppearance, and takes over from there.
--}}
@props(['class' => ''])

<button
    type="button"
    x-data
    x-on:click="$flux.dark = !$flux.dark"
    x-cloak
    :aria-pressed="$flux.dark.toString()"
    aria-label="Switch to light or dark theme"
    {{ $attributes->class(['group relative inline-flex h-8 w-16 shrink-0 items-center rounded-full p-1 shadow-inner outline-none transition-shadow duration-300 focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:ring-offset-2 focus-visible:ring-offset-transparent']) }}
>
    {{-- Day sky --}}
    <span
        class="absolute inset-0 overflow-hidden rounded-full transition-opacity duration-500 ease-in-out"
        :class="$flux.dark ? 'opacity-0' : 'opacity-100'"
        style="background: linear-gradient(120deg, #8ec9ec 0%, #cdeaf9 55%, #eef8fd 100%);"
    >
        <span class="absolute left-[34px] top-[7px] h-[9px] w-[17px] rounded-full bg-white/85"></span>
        <span class="absolute left-[41px] top-[4px] h-[7px] w-[11px] rounded-full bg-white/70"></span>
    </span>

    {{-- Night sky --}}
    <span
        class="absolute inset-0 overflow-hidden rounded-full transition-opacity duration-500 ease-in-out"
        :class="$flux.dark ? 'opacity-100' : 'opacity-0'"
        style="background: linear-gradient(120deg, #0b1229 0%, #182552 60%, #22306b 100%);"
    >
        <span class="absolute left-[11px] top-[6px] h-[3px] w-[3px] rounded-full bg-white/90"></span>
        <span class="absolute left-[18px] top-[12px] h-[2px] w-[2px] rounded-full bg-white/70"></span>
        <span class="absolute left-[13px] top-[18px] h-[2px] w-[2px] rounded-full bg-white/80"></span>
        <span class="absolute left-[22px] top-[6px] h-[2px] w-[2px] rounded-full bg-white/60"></span>
    </span>

    {{-- Sliding thumb --}}
    <span
        class="relative z-10 flex size-6 items-center justify-center rounded-full bg-white shadow-md transition-transform duration-500 ease-out"
        :class="$flux.dark ? 'translate-x-8' : 'translate-x-0'"
    >
        {{-- Flux's icon component only forwards a plain "class" attribute,
             so the crossfade lives on a wrapping span instead. --}}
        <span
            class="absolute transition-all duration-300"
            x-bind:class="$flux.dark ? 'scale-0 opacity-0 -rotate-45' : 'scale-100 opacity-100 rotate-0'"
        >
            <flux:icon.sun variant="micro" class="size-4 text-amber-500" />
        </span>
        <span
            class="absolute transition-all duration-300"
            x-bind:class="$flux.dark ? 'scale-100 opacity-100 rotate-0' : 'scale-0 opacity-0 rotate-45'"
        >
            <flux:icon.moon variant="micro" class="size-4 text-indigo-600" />
        </span>
    </span>
</button>
