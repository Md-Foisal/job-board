{{-- The rows of a workspace sidebar, drawn from App\Support\Navigation. --}}
@foreach ($sections as $section)
    @if ($section->heading)
        <x-sidebar-heading>{{ $section->heading }}</x-sidebar-heading>
    @endif

    @foreach ($section->items as $item)
        <flux:sidebar.item :icon="$item->icon" :href="$item->url" :current="$item->isCurrent()" :badge="$item->badge" wire:navigate>
            {{ $item->label }}
        </flux:sidebar.item>
    @endforeach
@endforeach
