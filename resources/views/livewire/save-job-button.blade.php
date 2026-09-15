<flux:button wire:click="toggle" variant="{{ $saved ? 'primary' : 'outline' }}" icon="{{ $saved ? 'bookmark-slash' : 'bookmark' }}">
    {{ $saved ? 'Saved' : 'Save' }}
</flux:button>
