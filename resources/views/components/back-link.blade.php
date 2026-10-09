{{--
    The link up to the parent page, above the title of a page one level
    below a list ("← Job postings" on a job's applicants). Inside a
    sidebar workspace this replaces a breadcrumb trail: the sidebar
    already says where you are, so only the step back is missing.
    The visible text is the parent page's title; a screen reader hears
    "Back to" before it, since the arrow says that only to the eye.
--}}
@props(['href'])

<a href="{{ $href }}" wire:navigate {{ $attributes->class('group inline-flex w-fit max-w-full items-center gap-1.5 rounded-control text-sm font-medium text-ink-muted transition-colors hover:text-ink') }}>
    <flux:icon.arrow-left class="size-4 shrink-0 transition-transform duration-200 ease-brand group-hover:-translate-x-0.5" aria-hidden="true" />
    <span class="truncate"><span class="sr-only">{{ __('Back to') }} </span>{{ $slot }}</span>
</a>
