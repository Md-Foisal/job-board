@props(['percent', 'showDetail' => false])

{{-- What the mark means goes with it every time: on screen where there
     is room, otherwise for screen readers only, since a title tooltip
     never shows on a phone. --}}
<span {{ $attributes->class('inline-flex flex-wrap items-center gap-2') }}>
    <span class="inline-flex items-center gap-1 rounded-full bg-success-50 px-2.5 py-1 text-xs font-medium text-success-700 dark:bg-success-950 dark:text-success-300">
        <flux:icon.bolt variant="micro" class="size-3.5" aria-hidden="true" />
        {{ __('Responsive employer') }}
    </span>
    <span @class([
        'text-sm text-ink-muted' => $showDetail,
        'sr-only' => ! $showDetail,
    ])>
        {{ __('Answered :percent% of recent applications within :days days.', [
            'percent' => $percent,
            'days' => \App\Services\EmployerResponsiveness::ANSWER_WITHIN_DAYS,
        ]) }}
    </span>
</span>
