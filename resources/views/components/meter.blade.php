{{--
    A bar that shows a share of a whole: profile completion, a step of the
    hiring funnel. The fill is the Sunset gradient on a neutral track.
    Decorative: the number it stands for is always written beside it.
--}}
@props(['percent', 'size' => 'md'])

<div {{ $attributes->class([
    'w-full overflow-hidden rounded-full bg-line',
    'h-1.5' => $size === 'sm',
    'h-2' => $size === 'md',
]) }} aria-hidden="true">
    <div class="bg-sunset h-full rounded-full" style="width: {{ max(0, min(100, $percent)) }}%"></div>
</div>
