@props([
    'status',
])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-success-700 dark:text-success-300']) }}>
        {{ $status }}
    </div>
@endif
