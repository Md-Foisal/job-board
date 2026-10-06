{{--
    An icon on a small square of brand tint, set beside a heading or a
    list entry to say what kind of thing it is: a role, a document, an
    alert. The icon is coloured, so it is the Sunset gradient. Decorative:
    the text next to it says the same thing in words.
--}}
@props(['icon', 'size' => 'md'])

<span {{ $attributes->class([
    'icon-sunset flex shrink-0 items-center justify-center rounded-control bg-brand-50 dark:bg-brand-950',
    'size-9' => $size === 'sm',
    'size-11' => $size === 'md',
]) }} aria-hidden="true">
    <flux:icon :name="$icon" variant="mini" />
</span>
