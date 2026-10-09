<x-layouts::auth.simple :title="$title ?? null">
    {{ $slot }}

    @isset($footer)
        <x-slot:footer>{{ $footer }}</x-slot:footer>
    @endisset
</x-layouts::auth.simple>
