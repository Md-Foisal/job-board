<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            {{ config('app.name') }}
        </x-mail::header>
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Footer: the same links as the HTML version. --}}
    <x-slot:footer>
        <x-mail::footer>
            {{ __('Privacy') }}: {{ route('privacy') }}
            {{ __('Contact us') }}: {{ route('contact') }}

            © {{ date('Y') }} {{ config('app.name') }}
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
