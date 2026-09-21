@props(['size' => 'sm'])

{{-- The icon alone reaches nobody using a screen reader, and a title
     tooltip never shows on a phone, so the words go in visually-hidden
     text rather than only in the title. --}}
<span {{ $attributes->class('inline-flex shrink-0 items-center text-brand-600 dark:text-brand-400') }} title="{{ __('Verified company') }}">
    <flux:icon.check-badge variant="mini" @class(['inline', 'size-4' => $size === 'sm', 'size-5' => $size === 'lg']) aria-hidden="true" />
    <span class="sr-only">{{ __('Verified company') }}</span>
</span>
