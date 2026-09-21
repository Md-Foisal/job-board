{{-- Where /dashboard lands someone with neither side set up yet: an
     employer who registered but never named a company, or someone whose
     every membership has ended. Replaces the starter kit's placeholder
     boxes, which gave them nothing to do. --}}
<x-layouts::app :title="__('Get started')">
    <div class="mx-auto max-w-3xl px-6 py-10">
        <flux:heading size="xl">{{ __('What would you like to do?') }}</flux:heading>
        <flux:subheading>{{ __('You can set up either side now and add the other whenever you need it.') }}</flux:subheading>

        <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="flex flex-col gap-4 rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300">
                    <flux:icon name="user" variant="mini" />
                </div>
                <div class="flex-1">
                    <flux:heading>{{ __('Find a job') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Build a profile once, apply in a few clicks, and see where every application stands.') }}</flux:text>
                </div>
                <form method="POST" action="{{ route('candidate.start') }}">
                    @csrf
                    <flux:button type="submit" variant="primary" class="w-full">{{ __('Start a candidate profile') }}</flux:button>
                </form>
            </div>

            <div class="flex flex-col gap-4 rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-700 dark:bg-brand-900/40 dark:text-brand-300">
                    <flux:icon name="building-office" variant="mini" />
                </div>
                <div class="flex-1">
                    <flux:heading>{{ __('Hire') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Set up your company, post jobs and review applicants with your team.') }}</flux:text>
                </div>
                <flux:button :href="route('companies.create')" class="w-full">{{ __('Create a company') }}</flux:button>
            </div>
        </div>
    </div>
</x-layouts::app>
