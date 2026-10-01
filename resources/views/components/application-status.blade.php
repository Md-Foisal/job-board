{{--
    An application's outcome badge, plus its review stage while the
    application is still active. Lived twice -- once in the My
    Applications list, once on the timeline page -- until a second copy
    made the duplication obvious: adding an outcome would have meant
    picking its colour in two files.

    The colour itself is not decided here but on the enum
    (ApplicationOutcomeStatus::color()), so any other view that shows a
    status without using this component still gets the same colour.

    @param \App\Models\Application $application
    @param bool $showStage  false to show only the outcome badge.
    @param bool $forCandidate  true on the candidate's own pages, where a
                               decision still inside its undo window has not
                               been made yet.
--}}
@props(['application', 'showStage' => true, 'forCandidate' => false])

@php
    $outcome = $forCandidate ? $application->outcomeForCandidate() : $application->outcome_status;
@endphp

<flux:badge :color="$outcome->color()">
    {{ $outcome->label() }}
</flux:badge>

@if ($showStage && $outcome === \App\Enums\ApplicationOutcomeStatus::Active)
    <flux:badge color="zinc" variant="pill">
        {{ $application->stage->label() }}
    </flux:badge>
@endif
