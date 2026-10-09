{{--
    The top of every page inside a workspace, after Shopify Polaris's
    Page header: an optional link back to the parent page, the title
    with its status beside it, an optional line under it, and the page's
    actions on the right -- secondary ones first, the one primary action
    last, at the far edge.

    The line under the title is for something the page cannot say
    otherwise (what the company sees, who reads this); a page that has
    nothing to add leaves it out rather than restating its title.

    The title side never gets narrower than 16rem: below that the
    actions move to a row of their own instead of running over a long
    title on a phone.

    Slots: title (when it needs markup, such as a link), status, media
    (a photo or logo before the title), actions, and the default slot for
    anything longer than one line under the title.
--}}
@props([
    'title' => null,
    'description' => null,
    'back' => null,
    'backLabel' => null,
])

<header {{ $attributes->class('flex flex-col gap-3') }}>
    @if ($back)
        <x-back-link :href="$back">{{ $backLabel }}</x-back-link>
    @endif

    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-4">
        <div class="flex min-w-0 flex-1 basis-64 items-center gap-4">
            {{ $media ?? '' }}

            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                    <flux:heading size="xl" level="1" class="font-display text-balance">{{ $title }}</flux:heading>
                    {{ $status ?? '' }}
                </div>

                @if (filled($description))
                    <flux:text class="mt-1 max-w-3xl">{{ $description }}</flux:text>
                @endif

                @if ($slot->isNotEmpty())
                    <div class="mt-1 max-w-3xl text-sm text-ink-muted">{{ $slot }}</div>
                @endif
            </div>
        </div>

        @isset($actions)
            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
</header>
