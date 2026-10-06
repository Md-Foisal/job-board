{{--
    The one container for a block of content on a page: a hairline border
    on the page colour, the card radius, and the same padding everywhere.
    "interactive" is for a card that is itself a link or opens one: it
    lifts on hover, so the whole surface reads as clickable. "subtle"
    puts the card on the surface colour, for a panel that sits beside
    the main content rather than being part of it.
--}}
@props([
    'as' => 'div',
    'interactive' => false,
    'subtle' => false,
    'padding' => 'md',
])

<{{ $as }} {{ $attributes->class([
    'rounded-card border border-line',
    'bg-surface' => $subtle,
    'bg-canvas' => ! $subtle,
    'p-4 sm:p-5' => $padding === 'sm',
    'p-5 sm:p-6' => $padding === 'md',
    'p-6 sm:p-8' => $padding === 'lg',
    'transition duration-200 ease-brand hover:-translate-y-0.5 hover:border-line-strong hover:shadow-lift' => $interactive,
]) }}>
    {{ $slot }}
</{{ $as }}>
