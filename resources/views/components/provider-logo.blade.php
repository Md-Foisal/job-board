{{--
    A sign-in provider's own logo, from its official asset pack
    (public/images/brand), in a light and a dark version. Google's G is
    the icon-only button from Google's sign-in assets, its viewBox
    narrowed to the 20px G -- the drawing itself is untouched, and its
    fill is the button's own, white or #131314. GitHub's mark is used as
    shipped, black on light and white on dark. Decorative: the
    provider's name is always written next to it.
--}}
@props(['provider'])

@php
    [$light, $dark] = $provider === \App\Enums\SignInProvider::Google
        ? ['google-g.svg', 'google-g-dark.svg']
        : ['github-mark.svg', 'github-mark-white.svg'];
@endphp

<img src="{{ asset('images/brand/'.$light) }}" alt="" width="20" height="20" {{ $attributes->class('size-5 shrink-0 object-contain dark:hidden') }}>
<img src="{{ asset('images/brand/'.$dark) }}" alt="" width="20" height="20" {{ $attributes->class('hidden size-5 shrink-0 object-contain dark:block') }}>
