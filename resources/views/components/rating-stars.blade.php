@props(['value', 'size' => 'sm'])

{{-- One label for the whole row: five star icons would otherwise be read
     out as five images, or as nothing at all. --}}
<span {{ $attributes->class('inline-flex shrink-0 items-center gap-0.5') }} role="img" aria-label="{{ __(':value out of 5', ['value' => $value]) }}">
    @foreach (range(1, 5) as $star)
        <flux:icon.star variant="mini" aria-hidden="true" @class([
            'size-4' => $size === 'sm',
            'size-5' => $size === 'lg',
            'text-warning-500' => $star <= round($value),
            'text-zinc-300 dark:text-zinc-700' => $star > round($value),
        ]) />
    @endforeach
</span>
