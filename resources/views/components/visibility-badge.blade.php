{{--
    Who can see a page, said the way products say it: a small badge next
    to the title -- "Private" with a lock, or "Public" with a globe --
    and the detail in a tooltip, instead of a sentence under the title.
    Focusable, so the tooltip opens from the keyboard too.
--}}
@props(['public' => false, 'tip'])

<flux:tooltip :content="$tip">
    <flux:badge size="sm" :icon="$public ? 'globe-alt' : 'lock-closed'" tabindex="0" {{ $attributes }}>
        {{ $public ? __('Public') : __('Private') }}
    </flux:badge>
</flux:tooltip>
