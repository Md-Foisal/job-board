<div>
    @auth
        <flux:modal.trigger name="report-modal-{{ $reportable->id }}">
            <flux:button variant="ghost" icon="flag">Report</flux:button>
        </flux:modal.trigger>

        <flux:modal name="report-modal-{{ $reportable->id }}" class="max-w-md">
            <div class="space-y-6">
                <flux:heading size="lg">Report this listing</flux:heading>

                <flux:select wire:model="reason" :label="__('Reason')" placeholder="Choose a reason">
                    @foreach ($this->reasons as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="flex justify-end gap-2">
                    <flux:button wire:click="submit" variant="primary">Submit report</flux:button>
                </div>
            </div>
        </flux:modal>
    @endauth
</div>
