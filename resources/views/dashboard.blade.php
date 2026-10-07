{{-- Where /dashboard lands someone with neither side set up yet: an
     employer who registered but never named a company, or someone whose
     every membership has ended. Replaces the starter kit's placeholder
     boxes, which gave them nothing to do. --}}
<x-layouts::app :title="__('Get started')">
    <x-page width="narrow">
        <x-page-header :title="__('What would you like to do?')" :description="__('You can set up either side now and add the other whenever you need it.')" />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-card class="flex flex-col gap-4">
                <x-icon-tile icon="user" />
                <div class="flex-1">
                    <flux:heading>{{ __('Find a job') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Build a profile once, apply in a few clicks, and see where every application stands.') }}</flux:text>
                </div>
                <form method="POST" action="{{ route('candidate.start') }}">
                    @csrf
                    <flux:button type="submit" variant="primary" class="w-full">{{ __('Start a candidate profile') }}</flux:button>
                </form>
            </x-card>

            <x-card class="flex flex-col gap-4">
                <x-icon-tile icon="building-office" />
                <div class="flex-1">
                    <flux:heading>{{ __('Hire') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Set up your company, post jobs and review applicants with your team.') }}</flux:text>
                </div>
                <flux:button :href="route('companies.create')" class="w-full">{{ __('Create a company') }}</flux:button>
            </x-card>
        </div>
    </x-page>
</x-layouts::app>
