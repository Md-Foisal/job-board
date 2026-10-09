{{--
    The heading of a page in the auth layout: the page's one h1, and an
    optional line under it for whatever the visitor needs to know before
    filling the form in.
--}}
@props([
    'title',
    'description' => null,
])

<div class="flex w-full flex-col gap-1.5 text-center">
    <flux:heading size="xl" level="1">{{ $title }}</flux:heading>
    @if (filled($description))
        <flux:subheading>{{ $description }}</flux:subheading>
    @endif
</div>
