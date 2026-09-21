<div>
    @auth
        <flux:modal.trigger :name="$this->modalName()">
            <flux:button variant="ghost" icon="flag">{{ __('Report') }}</flux:button>
        </flux:modal.trigger>

        <flux:modal :name="$this->modalName()" class="max-w-md">
            <div class="space-y-6">
                <flux:heading size="lg">{{ __('Report this :subject', ['subject' => $this->subjectNoun()]) }}</flux:heading>

                <flux:select wire:model="reason" :label="__('Reason')" :placeholder="__('Choose a reason')">
                    @foreach ($this->reasons as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="flex justify-end gap-2">
                    <flux:button wire:click="submit" variant="primary">{{ __('Submit report') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    @endauth
</div>
