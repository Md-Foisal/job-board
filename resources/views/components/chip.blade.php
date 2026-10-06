{{--
    Small labels, with one shape per meaning so they can be told apart
    at a glance:

      fact     a property of a job or person -- Hybrid, Full-time,
               London, 6+ years. Neutral, square corners.
      skill    a skill someone has or a job asks for. Round, on the
               brand tint, its name in the Sunset text gradient.
      missing  a skill the job asks for that the person does not list.
               Round, dashed outline.

    Status (Applied, Rejected, Approved ...) is not a chip: it uses
    flux:badge in its status colour, through application-status and
    posting-status.
--}}
@props(['variant' => 'fact'])

<span {{ $attributes->class([
    'inline-flex items-center gap-1 whitespace-nowrap text-meta font-medium',
    'rounded-lg border border-line bg-surface px-2.5 py-0.5 text-ink-muted' => $variant === 'fact',
    'rounded-full bg-brand-50 px-2.5 py-0.5 dark:bg-brand-950' => $variant === 'skill',
    'rounded-full border border-dashed border-line-strong px-2.5 py-0.5 text-ink-muted' => $variant === 'missing',
]) }}>
    @if ($variant === 'skill')
        <span class="text-sunset-small">{{ $slot }}</span>
    @else
        {{ $slot }}
    @endif
</span>
