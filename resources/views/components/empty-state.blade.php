{{--
    What a list or section shows when it has nothing in it yet. Built on
    the parts of GitHub Primer's Blankslate -- an icon, a heading, a line
    of description and the step that fills it -- because an empty state
    is where a person learns what belongs here and how to add it (NN/g:
    an empty state should say what is going on and link straight to the
    task that fills it). The heading level follows the page's outline;
    an action that is a Livewire call rather than a link goes in the
    "actions" slot.
--}}
@props([
    'heading',
    'icon' => null,
    'level' => 2,
    'actionHref' => null,
    'actionLabel' => null,
])

<div {{ $attributes->class('flex flex-col items-center rounded-card border border-dashed border-line-strong px-6 py-12 text-center') }}>
    @if ($icon)
        <span class="mb-4 flex size-11 items-center justify-center rounded-control bg-surface text-ink-muted ring-1 ring-line" aria-hidden="true">
            <flux:icon :name="$icon" class="size-5" />
        </span>
    @endif

    <h{{ $level }} class="text-subheading text-ink">{{ $heading }}</h{{ $level }}>

    @if ($slot->isNotEmpty())
        <p class="mt-1 max-w-md text-body text-ink-muted">{{ $slot }}</p>
    @endif

    @if ($actionHref || isset($actions))
        <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
            @if ($actionHref)
                <flux:button :href="$actionHref" variant="primary" size="sm" wire:navigate>{{ $actionLabel }}</flux:button>
            @endif
            {{ $actions ?? '' }}
        </div>
    @endif
</div>
