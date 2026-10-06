<div>
    @if ($compact)
        {{-- One fixed name and a pressed state, the way a toggle button is
             announced; the filled icon is the visible half of the same. --}}
        <flux:button
            wire:click="toggle"
            variant="ghost"
            size="sm"
            square
            icon="bookmark"
            :icon-variant="$saved ? 'solid' : 'outline'"
            :aria-label="__('Save job')"
            aria-pressed="{{ $saved ? 'true' : 'false' }}"
            :class="$saved ? 'icon-sunset' : ''"
        />
    @else
        <flux:button wire:click="toggle" variant="{{ $saved ? 'primary' : 'outline' }}" icon="{{ $saved ? 'bookmark-slash' : 'bookmark' }}">
            {{ $saved ? 'Saved' : 'Save' }}
        </flux:button>
    @endif
</div>
