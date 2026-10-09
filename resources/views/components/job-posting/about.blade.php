{{-- The body of a job page: the description, then the skills split into
     required and nice to have. Shared with the employer's preview. The
     description is stored already cleaned (SanitizedHtml), and so is a
     preview's, which goes through the same cast. --}}
@props(['jobPosting'])

@php
    $requiredSkills = $jobPosting->skills->filter(fn ($skill) => $skill->pivot->importance === \App\Enums\SkillImportance::Required);
    $niceSkills = $jobPosting->skills->diff($requiredSkills);
@endphp

<section {{ $attributes->class('mt-8 first:mt-0') }} aria-labelledby="job-description-heading">
    <h2 id="job-description-heading" class="font-display text-heading text-ink">{{ __('About the job') }}</h2>
    <div class="prose prose-zinc mt-4 max-w-none dark:prose-invert">
        <div class="prose-content">{!! $jobPosting->description !!}</div>
    </div>
</section>

@if ($jobPosting->skills->isNotEmpty())
    <section class="mt-10" aria-labelledby="job-skills-heading">
        <h2 id="job-skills-heading" class="font-display text-heading text-ink">{{ __('Skills') }}</h2>
        @foreach ([__('Required') => $requiredSkills, __('Nice to have') => $niceSkills] as $label => $group)
            @if ($group->isNotEmpty())
                <h3 class="mt-4 text-meta font-medium uppercase tracking-wide text-ink-muted">{{ $label }}</h3>
                <ul class="mt-2 flex flex-wrap gap-2">
                    @foreach ($group as $skill)
                        <li><x-chip variant="skill">{{ $skill->name }}</x-chip></li>
                    @endforeach
                </ul>
            @endif
        @endforeach
    </section>
@endif
