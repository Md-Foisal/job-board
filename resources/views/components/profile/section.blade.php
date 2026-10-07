{{--
    One section of a candidate's profile -- About, Experience, Education,
    Skills: a card with the section's name on top and, for the candidate,
    the buttons that add to it. Lists inside reach the card's edges and
    bring their own dividers. At level 1 the section is the whole page
    (the Experience page, say), so its name is the page title.
--}}
@props(['heading', 'id' => null, 'level' => 2])

<x-card padding="none" {{ $attributes->class('overflow-hidden') }} :id="$id">
    <div class="flex flex-wrap items-center justify-between gap-3 px-5 pt-5 pb-4 sm:px-6">
        <flux:heading :size="$level === 1 ? 'xl' : 'lg'" :level="$level" :class="$level === 1 ? 'font-display' : ''">{{ $heading }}</flux:heading>

        @isset($actions)
            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>

    {{ $slot }}
</x-card>
