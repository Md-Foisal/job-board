{{--
    A candidate's work and study history as an employer reads it: the
    Experience and Education sections of their profile, newest first,
    drawn with the same entries the profile itself uses. A section the
    candidate has left empty still shows, with a line saying so, so the
    reader can tell "nothing listed" from "nothing loaded". Certifications
    and projects, when passed, follow in LinkedIn's order and are left out
    when empty, as optional sections are on a profile.
--}}
@props(['experience', 'education', 'certifications' => null, 'projects' => null])

<x-profile.section :heading="__('Experience')">
    @if ($experience->isEmpty())
        <p class="border-t border-line px-5 py-4 text-sm text-ink-muted sm:px-6">{{ __('No work experience on their profile.') }}</p>
    @else
        <ul class="divide-y divide-line border-t border-line">
            @foreach ($experience as $record)
                <x-profile.experience-item :record="$record" />
            @endforeach
        </ul>
    @endif
</x-profile.section>

<x-profile.section :heading="__('Education')">
    @if ($education->isEmpty())
        <p class="border-t border-line px-5 py-4 text-sm text-ink-muted sm:px-6">{{ __('No education on their profile.') }}</p>
    @else
        <ul class="divide-y divide-line border-t border-line">
            @foreach ($education as $record)
                <x-profile.education-item :record="$record" />
            @endforeach
        </ul>
    @endif
</x-profile.section>

@if ($certifications?->isNotEmpty())
    <x-profile.section :heading="__('Licences & certifications')">
        <ul class="divide-y divide-line border-t border-line">
            @foreach ($certifications as $certification)
                <x-profile.certification-item :certification="$certification" />
            @endforeach
        </ul>
    </x-profile.section>
@endif

@if ($projects?->isNotEmpty())
    <x-profile.section :heading="__('Projects')">
        <ul class="divide-y divide-line border-t border-line">
            @foreach ($projects as $project)
                <x-profile.project-item :project="$project" />
            @endforeach
        </ul>
    </x-profile.section>
@endif
