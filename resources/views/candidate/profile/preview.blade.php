{{--
    The candidate's profile as a company reads it, after LinkedIn's "View
    as": a thin bar says whose eyes these are and leads back, and the
    rest is the profile itself, with the name as the page's title.
--}}
<x-layouts::app :title="__('Preview as employer')">
    <x-page>
        <x-card subtle padding="sm" class="flex flex-wrap items-center justify-between gap-3">
            <p class="flex items-center gap-2 text-sm text-ink-soft">
                <flux:icon.eye variant="mini" class="shrink-0 text-ink-muted" aria-hidden="true" />
                {{ __('This is how a company sees your profile when you apply, next to the CV you send.') }}
            </p>
            <flux:button :href="route('candidate.profile.edit')" wire:navigate size="sm" icon="arrow-left">{{ __('Back to editing') }}</flux:button>
        </x-card>

        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3">
            <x-candidate-profile :profile="$candidateProfile" mode="viewer" :heading-level="1" class="lg:col-span-2" />
        </div>
    </x-page>
</x-layouts::app>
