{{--
    A company's logo, or its first letter on a tint when it has none. The
    tint is picked from the company's id, so one company keeps its colour
    on every page while a list of companies without logos does not turn
    into a column of identical squares. Decorative: the company's name is
    always written next to it.
--}}
@props(['company', 'size' => 'md'])

@php
    $tints = [
        'bg-indigo-50 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-200',
        'bg-sky-50 text-sky-800 dark:bg-sky-950 dark:text-sky-200',
        'bg-purple-50 text-purple-800 dark:bg-purple-950 dark:text-purple-200',
        'bg-green-50 text-green-800 dark:bg-green-950 dark:text-green-200',
        'bg-brand-50 text-sunset-small dark:bg-brand-950',
    ];
@endphp

<span {{ $attributes->class([
    'flex shrink-0 items-center justify-center overflow-hidden font-semibold',
    'size-9 rounded-lg text-sm' => $size === 'sm',
    'size-11 rounded-xl text-base' => $size === 'md',
    'size-14 rounded-2xl text-xl' => $size === 'lg',
    'size-24 rounded-3xl text-3xl' => $size === 'xl',
    $tints[$company->id % count($tints)] => ! $company->logo_path,
    'bg-canvas ring-1 ring-line' => (bool) $company->logo_path,
]) }} aria-hidden="true">
    @if ($company->logo_path)
        <img src="{{ \Illuminate\Support\Facades\Storage::url($company->logo_path) }}" alt="" class="size-full object-cover">
    @else
        {{ \Illuminate\Support\Str::of($company->name)->substr(0, 1)->upper() }}
    @endif
</span>
