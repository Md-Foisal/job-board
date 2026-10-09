{{--
    Small labels, with one shape per meaning so they can be told apart
    at a glance:

      fact     a property of a job or person -- Hybrid, Full-time,
               London, 6+ years. Neutral, square corners.
      skill    a skill someone has or a job asks for. Round, on the
               brand tint, its name in the Sunset text gradient.
      matched  a skill the job asks for that the person has. Round, in
               the success colour, since here the colour is the answer,
               with a tick so the answer is not colour alone.
      missing  a skill the job asks for that the person does not list.
               Round, dashed outline, a cross; amber when the job marks
               it required.

    Status (Applied, Rejected, Approved ...) is not a chip: it uses
    flux:badge in its status colour, through application-status and
    posting-status.
--}}
@props(['variant' => 'fact', 'required' => false])

<span {{ $attributes->class([
    'inline-flex items-center gap-1 whitespace-nowrap text-meta font-medium',
    'rounded-lg border border-line bg-surface px-2.5 py-0.5 text-ink-muted' => $variant === 'fact',
    'rounded-full bg-brand-50 px-2.5 py-0.5 dark:bg-brand-950' => $variant === 'skill',
    'rounded-full bg-success-50 px-2.5 py-0.5 text-success-700 dark:bg-success-950 dark:text-success-300' => $variant === 'matched',
    'rounded-full border border-dashed px-2.5 py-0.5' => $variant === 'missing',
    'border-line-strong text-ink-muted' => $variant === 'missing' && ! $required,
    'border-warning-300 text-warning-700 dark:border-warning-700 dark:text-warning-300' => $variant === 'missing' && $required,
]) }}>
    @if ($variant === 'skill')
        <span class="text-sunset-small">{{ $slot }}</span>
    @elseif ($variant === 'matched' || $variant === 'missing')
        {{-- A tick or a cross as well as the colour, so the answer does
             not rest on colour alone (WCAG 1.4.1). --}}
        <flux:icon :name="$variant === 'matched' ? 'check' : 'x-mark'" variant="micro" class="-ms-0.5 size-3.5 shrink-0" aria-hidden="true" />
        {{ $slot }}
    @else
        {{ $slot }}
    @endif
</span>
