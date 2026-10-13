{{--
    One "Continue with Google / GitHub" button: a link when given an href,
    otherwise a submit button for a form around it. The frame and colours
    are .btn-provider in app.css; the logo is x-provider-logo.
--}}
@props(['provider', 'href' => null])

@php
    $classes = 'btn-provider btn-provider-'.$provider->value;
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
@else
    <button {{ $attributes->merge(['type' => 'submit'])->class($classes) }}>
@endif
        <x-provider-logo :provider="$provider" />
        <span>{{ $slot }}</span>
@if ($href)
    </a>
@else
    </button>
@endif
