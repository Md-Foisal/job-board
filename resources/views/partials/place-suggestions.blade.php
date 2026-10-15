{{--
    The places a "where" box suggests (App\Support\PlaceSuggestions), in a
    list under the box. It opens when the box gets focus, so the places
    with open jobs show before anything is typed; the arrow keys move
    through it, Enter or a click fills the box, Escape closes it. The
    wrapper around the box and this list holds placesOpen. $typed is the
    box's current text.
--}}
@if ($places !== [])
    <div
        x-show="placesOpen"
        x-cloak
        x-ref="places"
        x-on:keydown.down.prevent="$focus.within($refs.places).wrap().next()"
        x-on:keydown.up.prevent="$focus.within($refs.places).wrap().previous()"
        class="absolute inset-x-0 top-full z-30 mt-2 overflow-hidden rounded-control border border-line bg-canvas py-1 text-left shadow-lift sm:left-auto sm:w-64"
    >
        <p class="px-4 pt-2 pb-1 text-meta text-ink-muted">{{ __('Places with open jobs') }}</p>

        @foreach ($places as $place)
            <button
                type="button"
                wire:key="place-{{ $model }}-{{ $loop->index }}"
                x-on:click="$wire.set(@js($model), @js($place['value'])); placesOpen = false"
                class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-ink transition-colors hover:bg-surface focus-visible:bg-surface focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-accent"
            >
                @if ($place['remote'])
                    <flux:icon.globe-alt class="size-4 shrink-0 text-ink-muted" aria-hidden="true" />
                @else
                    <flux:icon.map-pin class="size-4 shrink-0 text-ink-muted" aria-hidden="true" />
                @endif
                {{-- What was typed in regular weight, the rest of the name in
                     bold: the eye goes to the part that tells places apart. --}}
                @php
                    $typedAt = trim($typed) !== '' && preg_match('/(?:^|\s)\K'.preg_quote(trim($typed), '/').'/iu', $place['label'], $hit, PREG_OFFSET_CAPTURE)
                        ? mb_strlen(substr($place['label'], 0, $hit[0][1]))
                        : null;
                @endphp
                <span class="truncate font-semibold">
                    @if ($typedAt === null)
                        {{ $place['label'] }}
                    @else
                        {{ mb_substr($place['label'], 0, $typedAt) }}<span class="font-normal">{{ mb_substr($place['label'], $typedAt, mb_strlen(trim($typed))) }}</span>{{ mb_substr($place['label'], $typedAt + mb_strlen(trim($typed))) }}
                    @endif
                </span>
                <span class="ms-auto shrink-0 text-meta text-ink-muted">{{ trans_choice(':count job|:count jobs', $place['jobs']) }}</span>
            </button>
        @endforeach
    </div>
@endif
