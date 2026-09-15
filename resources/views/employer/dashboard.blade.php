<x-layouts::employer :company="$company" :title="$company->name">
    <div class="mx-auto max-w-5xl">
        <flux:heading size="xl" class="font-display">{{ $company->name }}</flux:heading>
        <flux:text class="mt-1">{{ __('Your company workspace.') }}</flux:text>
    </div>
</x-layouts::employer>
