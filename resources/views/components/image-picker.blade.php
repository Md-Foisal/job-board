{{--
    Choosing a picture -- a person's photo, a company's logo, a cover --
    drawn as the picture itself with a button beside it, instead of the
    browser's own "Choose file / No file chosen" box. The new picture
    shows the moment it is picked, before anything is saved, as LinkedIn
    and GitHub do; the file name replaces the size hint so it is clear
    which file will go.

    The real file input is hidden and opened by the button, so it is
    still sent with whatever form holds it, and the server's rules
    (ImageUploads) stay the only check. A pick is announced as an
    "image-picked" browser event, for a page that previews the picture
    somewhere else too.

    @param string      $name     the file field's name.
    @param string      $label
    @param string|null $current  URL of the picture saved now.
    @param string      $shape    circle (photo), square (logo) or wide (cover).
    @param string|null $hint     the size and format line.
    Slot: what shows when there is no picture yet (initials, a colour).
--}}
@props([
    'name',
    'label',
    'current' => null,
    'shape' => 'circle',
    'hint' => null,
])

@php
    $id = 'image-picker-'.$name;
@endphp

<div
    {{ $attributes->class('flex flex-col gap-2') }}
    x-data="{ preview: null, file: null }"
    x-on:change="
        if ($event.target !== $refs.input) return;
        const picked = $refs.input.files[0] ?? null;
        file = picked?.name ?? null;
        preview = picked ? URL.createObjectURL(picked) : null;
        $dispatch('image-picked', { name: @js($name), url: preview });
    "
>
    <div @class([
        'flex gap-4',
        'items-center' => $shape !== 'wide',
        'flex-col' => $shape === 'wide',
    ])>
        <div @class([
            'relative flex shrink-0 items-center justify-center overflow-hidden bg-surface ring-1 ring-line',
            'size-20 rounded-full' => $shape === 'circle',
            'size-20 rounded-2xl' => $shape === 'square',
            'h-28 w-full rounded-control' => $shape === 'wide',
        ])>
            <template x-if="preview">
                <img :src="preview" alt="" class="size-full object-cover">
            </template>

            <div x-show="! preview" class="flex size-full items-center justify-center">
                @if ($current)
                    <img src="{{ $current }}" alt="" class="size-full object-cover">
                @else
                    {{ $slot }}
                @endif
            </div>
        </div>

        <div class="min-w-0">
            <label for="{{ $id }}" class="text-sm font-medium text-ink">{{ $label }}</label>

            <div class="mt-2 flex flex-wrap items-center gap-2">
                <flux:button size="sm" icon="camera" type="button" x-on:click="$refs.input.click()">
                    <span x-show="! file">{{ $current ? __('Change') : __('Upload') }}</span>
                    <span x-show="file" x-cloak>{{ __('Choose another') }}</span>
                </flux:button>

                <flux:button size="sm" variant="ghost" type="button" x-show="file" x-cloak
                    x-on:click="$refs.input.value = ''; $refs.input.dispatchEvent(new Event('change', { bubbles: true }))">
                    {{ __('Undo') }}
                </flux:button>
            </div>

            <p class="mt-2 truncate text-xs text-ink-muted" x-text="file ?? @js($hint)">{{ $hint }}</p>
        </div>
    </div>

    <input
        x-ref="input"
        id="{{ $id }}"
        type="file"
        name="{{ $name }}"
        accept="{{ \App\Support\ImageUploads::ACCEPT }}"
        class="sr-only"
        tabindex="-1"
    >

    <flux:error :name="$name" />
</div>
