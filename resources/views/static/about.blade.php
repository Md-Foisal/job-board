{{-- Everything here is something the product does today. Pay is not
     claimed to be on every posting: an employer may mark it negotiable. --}}
@php
    $forSeekers = [
        ['icon' => 'puzzle-piece', 'title' => __('See how you match before you apply'), 'text' => __('Each job shows which of its skills you have and which you are missing. When you apply, the employer sees the same skills match.')],
        ['icon' => 'clock', 'title' => __('Follow every application'), 'text' => __('Each application has a timeline of where it stands, so you are not left waiting in silence.')],
        ['icon' => 'banknotes', 'title' => __('Compare pay'), 'text' => __('When an employer gives a pay range, it is shown in the job’s own currency, and job search can filter by it.')],
        ['icon' => 'bell', 'title' => __('Hear about new jobs'), 'text' => __('Save a search as a job alert, and new jobs that match it come to your inbox.')],
    ];
@endphp

<x-static-page
    :heading="__('About')"
    :lead="__('A job board for professional and technical roles, built around one idea: the person applying deserves to know as much as the person hiring.')"
>
    <x-prose-section :heading="__('For people looking for work')">
        <ul class="mt-6 grid gap-6 sm:grid-cols-2">
            @foreach ($forSeekers as $item)
                <li class="flex gap-4">
                    <x-icon-tile :icon="$item['icon']" size="sm" />
                    <div>
                        <h3 class="text-subheading text-ink">{{ $item['title'] }}</h3>
                        <p class="mt-1 text-meta text-ink-muted">{{ $item['text'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </x-prose-section>

    <x-prose-section :heading="__('For people hiring')">
        <p>
            {{ __('Post a job, see each applicant’s skills next to what the job asks for, and work through them with your team. Applicants are never scored or ranked by AI for you: the decision is yours.') }}
        </p>
        <p>
            <a href="{{ route('employers') }}" class="inline-flex items-center gap-1 font-medium text-sunset-small hover:underline" wire:navigate>
                {{ __('How hiring works') }}
                <flux:icon.arrow-right variant="micro" class="size-4" aria-hidden="true" />
            </a>
        </p>
    </x-prose-section>

    <x-prose-section :heading="__('Keeping it honest')">
        <p>
            {{ __('Our staff check that a company is real before it gets the Verified badge, and read a new company’s job posts before they go public. Anyone signed in can report a job or a company that looks wrong.') }}
        </p>
        <p>
            {{ __('Reviews of a company’s hiring process come only from people who applied there, and our team reads each one before it appears. A company can answer a review in public, but it cannot remove it.') }}
        </p>
    </x-prose-section>

    <x-prose-section :heading="__('How we use AI')">
        <p>
            {{ __('When you ask it to, our AI reads a CV to fill in your profile, explains a match in words, or suggests better wording. It also points our team to reviews that may break the rules. It never decides who gets a job, and a person makes every moderation decision.') }}
            <a href="{{ route('privacy') }}" class="font-medium text-sunset-small hover:underline" wire:navigate>{{ __('The privacy policy') }}</a>
            {{ __('says exactly what is sent, and to whom.') }}
        </p>
    </x-prose-section>
</x-static-page>
