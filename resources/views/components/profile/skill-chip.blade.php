{{--
    A skill on a candidate's profile with the level they gave it, so a
    reader can tell "uses it daily" from "has tried it". The variant is
    the chip's own: skill on the profile, matched or missing when an
    employer reads it against a posting.
--}}
@props(['skill', 'variant' => 'skill'])

<x-chip :variant="$variant" {{ $attributes }}>
    {{ $skill->name }}
    @if ($skill->pivot?->proficiency)
        <span class="font-normal text-ink-muted">&middot; {{ $skill->pivot->proficiency->label() }}</span>
    @endif
</x-chip>
