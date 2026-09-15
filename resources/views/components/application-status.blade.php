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
--}}
@props(['application', 'showStage' => true])

<flux:badge :color="$application->outcome_status->color()">
    {{ $application->outcome_status->label() }}
</flux:badge>

@if ($showStage && $application->outcome_status === \App\Enums\ApplicationOutcomeStatus::Active)
    <flux:badge color="zinc" variant="pill">
        {{ $application->stage->label() }}
    </flux:badge>
@endif
