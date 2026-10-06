{{--
    One count per day as a chart, with the same numbers as a table one
    click away. The canvas is drawn by the performanceChart Alpine
    component; its aria-label carries the gist for anyone who cannot see
    it, and the table carries every value.

    @param string $title       what is counted, e.g. "Views per day"
    @param array  $series      date (Y-m-d) => count, every day of the range
    @param string $valueLabel  the table's column heading, e.g. "Views"
    @param string $type        "line", or "bar" for small counts
--}}
@props(['title', 'series', 'valueLabel', 'type' => 'line'])

@php
    $dates = collect(array_keys($series))->map(fn ($date) => \Carbon\CarbonImmutable::parse($date));
    $values = array_values($series);
    $total = array_sum($values);
    $peak = $values === [] ? 0 : max($values);
    $peakDate = $peak > 0 ? $dates[array_search($peak, $values, true)] : null;

    $summary = $peakDate
        ? __(':title: :total in total, highest :peak on :date.', ['title' => $title, 'total' => $total, 'peak' => $peak, 'date' => $peakDate->format(\App\Support\DateFormat::DAY)])
        : __(':title: none in this period.', ['title' => $title]);
@endphp

<figure
    {{ $attributes->class('rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900') }}
    x-data="performanceChart(@js([
        'type' => $type,
        'labels' => $dates->map->format(\App\Support\DateFormat::DAY_SHORT)->all(),
        'dates' => $dates->map->format(\App\Support\DateFormat::DAY)->all(),
        'values' => $values,
        'label' => $valueLabel,
    ]))"
>
    <figcaption class="flex items-center justify-between gap-4">
        <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $title }}</span>
        <button
            type="button"
            class="text-sm text-zinc-600 hover:text-brand-700 hover:underline dark:text-zinc-400 dark:hover:text-brand-400"
            x-on:click="showTable = ! showTable"
            x-bind:aria-expanded="showTable"
            x-text="showTable ? @js(__('Show as chart')) : @js(__('Show as table'))"
        >{{ __('Show as table') }}</button>
    </figcaption>

    <div class="mt-4 h-48" x-show="! showTable">
        <canvas x-ref="canvas" role="img" aria-label="{{ $summary }}"></canvas>
    </div>

    <div class="mt-4 max-h-64 overflow-y-auto" x-show="showTable" x-cloak>
        <table class="w-full text-sm">
            <caption class="sr-only">{{ $title }}</caption>
            <thead>
                <tr class="border-b border-zinc-200 dark:border-zinc-800">
                    <th scope="col" class="py-2 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Day') }}</th>
                    <th scope="col" class="py-2 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ $valueLabel }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($series as $date => $value)
                    <tr class="border-b border-zinc-100 last:border-0 dark:border-zinc-800/60">
                        <td class="py-1.5 text-zinc-700 dark:text-zinc-300">{{ \Carbon\CarbonImmutable::parse($date)->format(\App\Support\DateFormat::DAY) }}</td>
                        <td class="py-1.5 text-end tabular-nums text-zinc-900 dark:text-zinc-100">{{ $value }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</figure>
