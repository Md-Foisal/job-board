{{--
    The trail on public pages whose place in a hierarchy is real and
    where people often arrive straight from a search engine: a job
    (Jobs › Category › title) and a category (Jobs › Category). There is
    no Home crumb: the logo already goes home (NN/g). Pages inside a
    sidebar workspace use <x-back-link> instead.

    @param array $items  [['label' => 'Jobs', 'url' => route('jobs.index')], ['label' => 'Senior Laravel Developer']]
                         An item with no 'url' is the current page: it is
                         rendered as plain text, not a link.
--}}
@props(['items' => []])

<nav aria-label="{{ __('Breadcrumb') }}" {{ $attributes->class('min-w-0 text-sm text-ink-muted') }}>
    <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1">
        @foreach ($items as $item)
            <li class="flex min-w-0 items-center gap-x-1.5">
                @unless ($loop->first)
                    {{-- Decoration: a screen reader already announces a list of links. --}}
                    <flux:icon.chevron-right class="size-3.5 shrink-0 text-ink-muted/70" aria-hidden="true" />
                @endunless

                @if (! empty($item['url']))
                    <a href="{{ $item['url'] }}" class="hover:text-sunset-small" wire:navigate>{{ $item['label'] }}</a>
                @else
                    <span class="truncate text-ink-soft" aria-current="page">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
