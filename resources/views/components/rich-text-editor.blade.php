{{--
    A writing surface for the few fields that are genuinely documents --
    a job description, a company's "about", what someone did in a role --
    rather than for every text box in the app. A two-line introduction
    does not need bullet lists, and putting an editor everywhere is its
    own kind of clutter.

    Works in both kinds of form: pass `wire` for a Livewire property, or
    `name` for an ordinary POST. Whatever comes back is sanitized server
    side before it is stored -- the toolbar is a convenience, never a
    trust boundary.

    @param string|null $wire      Livewire property to write into
    @param string|null $name      form field name, for non-Livewire forms
    @param string      $value     existing HTML
    @param bool        $headings  false for shorter pieces like a cover letter
--}}
@props([
    'wire' => null,
    'name' => null,
    'value' => '',
    'label' => null,
    'description' => null,
    'headings' => true,
])

@php
    $inputId = $name ?? $wire ?? 'rich-text';
    $errorKey = $name ?? $wire;
@endphp

<div
    x-data="richTextEditor({ content: @js($value), wireModel: @js($wire), headings: @js($headings) })"
    x-on:destroy="destroy()"
    wire:ignore
    class="flex flex-col gap-2"
>
    @if ($label)
        <flux:label for="{{ $inputId }}">{{ $label }}</flux:label>
    @endif

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-xs dark:border-white/10 dark:bg-white/10">
        <div class="flex flex-wrap items-center gap-1 border-b border-zinc-200 bg-zinc-50 px-2 py-1.5 dark:border-white/10 dark:bg-white/5">
            @php
                $tools = [
                    ['bold', __('Bold'), 'bold'],
                    ['italic', __('Italic'), 'italic'],
                ];

                if ($headings) {
                    $tools[] = ['h3', __('Heading'), 'h2'];
                    $tools[] = ['h4', __('Subheading'), 'h3'];
                }

                $tools[] = ['bulletList', __('Bulleted list'), 'list-bullet'];
                $tools[] = ['orderedList', __('Numbered list'), 'numbered-list'];
            @endphp

            @foreach ($tools as [$command, $title, $icon])
                {{-- mousedown.prevent, not click alone: pressing a toolbar
                     button otherwise pulls focus out of the writing surface
                     before the command runs, so the formatting lands on
                     nothing and the next thing typed goes nowhere. Every
                     editor toolbar has to do this. --}}
                <button
                    type="button"
                    x-on:mousedown.prevent="run('{{ $command }}')"
                    x-bind:class="active.{{ $command }} ? 'bg-brand-100 text-brand-800 dark:bg-brand-900 dark:text-brand-200' : 'text-zinc-600 hover:bg-zinc-200 dark:text-zinc-300 dark:hover:bg-white/10'"
                    class="flex size-8 items-center justify-center rounded transition"
                    title="{{ $title }}"
                    aria-label="{{ $title }}"
                    x-bind:aria-pressed="active.{{ $command }} ? 'true' : 'false'"
                >
                    <flux:icon :name="$icon" variant="micro" />
                </button>
            @endforeach
        </div>

        <div x-ref="surface" id="{{ $inputId }}"></div>
    </div>

    @if ($name)
        {{-- Plain forms post the value like any other field. --}}
        <input type="hidden" name="{{ $name }}" x-bind:value="html" value="{{ $value }}">
    @endif

    @if ($description)
        <flux:text size="sm">{{ $description }}</flux:text>
    @endif

    @error($errorKey)
        <flux:text size="sm" class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
    @enderror
</div>
