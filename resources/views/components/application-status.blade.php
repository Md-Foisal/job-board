{{--
    Where an application stands, as a badge. Used wherever an application
    is listed or opened, so one status never reads two ways.

    The candidate sees one pill: the step their application has reached,
    or how it ended, in the words Indeed's tracker uses -- "Not selected"
    rather than "Rejected", the same news the decision email gives.
    Being turned down is neutral grey, not red: it is not an error the
    candidate has to fix, and red would shout from a list they open
    often. An offer and a hire are the good news, in green. A decision
    still inside its undo window has not been made yet, as far as the
    candidate is concerned.

    The company sees the outcome and, while it is still open, the stage
    beside it. The outcome's colour is decided on the enum
    (ApplicationOutcomeStatus::color()), so any other view that shows it
    without this component gets the same colour.

    @param \App\Models\Application $application
    @param bool $forCandidate  true on the candidate's own pages.
--}}
@props(['application', 'forCandidate' => false])

@if ($forCandidate)
    @php
        // No default arm: a new outcome or stage fails here rather than
        // showing the candidate a wrong word.
        [$label, $color] = match ($application->outcomeForCandidate()) {
            \App\Enums\ApplicationOutcomeStatus::Active => match ($application->stage) {
                \App\Enums\ApplicationStage::New => [__('Applied'), 'zinc'],
                \App\Enums\ApplicationStage::Shortlisted, \App\Enums\ApplicationStage::Interview => [__($application->stage->label()), 'zinc'],
                \App\Enums\ApplicationStage::Offer => [__('Offer'), 'green'],
            },
            \App\Enums\ApplicationOutcomeStatus::Hired => [__('Hired'), 'green'],
            \App\Enums\ApplicationOutcomeStatus::Rejected => [__('Not selected'), 'zinc'],
            \App\Enums\ApplicationOutcomeStatus::Withdrawn => [__('Withdrawn'), 'zinc'],
        };
    @endphp

    <flux:badge :color="$color">{{ $label }}</flux:badge>
@else
    <flux:badge :color="$application->outcome_status->color()">
        {{ $application->outcome_status->label() }}
    </flux:badge>

    @if ($application->outcome_status === \App\Enums\ApplicationOutcomeStatus::Active)
        <flux:badge color="zinc" variant="pill">
            {{ $application->stage->label() }}
        </flux:badge>
    @endif
@endif
