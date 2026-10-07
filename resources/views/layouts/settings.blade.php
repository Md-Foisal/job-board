{{--
    The frame of the settings pages. Settings belong to the person, not
    to a company, but someone who opens them from a company workspace
    stays inside it, with its sidebar, rather than being dropped onto the
    candidate side -- the same as their recruiter profile. The workspace
    is named by ?company= and used only when they are an active member
    of it; otherwise, and when they came from their own side, the
    personal frame.

    Rendered on the first load only, like every layout of a full-page
    Livewire component; the pages keep ?company= in their tab links so a
    move between tabs stays in the same frame.
--}}
@props(['title' => null])

@php
    $workspace = auth()->check()
        ? \App\Support\Navigation\Navigation::workspaceFrom(auth()->user(), request()->query('company'))
        : null;
@endphp

@if ($workspace)
    <x-layouts::employer :company="$workspace" :title="$title">
        {{ $slot }}
    </x-layouts::employer>
@else
    <x-layouts::app :title="$title">
        {{ $slot }}
    </x-layouts::app>
@endif
