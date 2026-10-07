<?php

namespace App\Support;

use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;
use App\Models\Application;
use App\Models\ApplicationEvent;

/**
 * How one change to an application reads to the candidate: the company
 * did it, never a named member of its team, and the candidate's own
 * withdrawal is theirs. The application page's history and the
 * dashboard's recent changes say it the same way.
 */
final class CandidateTimeline
{
    public static function line(ApplicationEvent $event, Application $application, int $candidateUserId): string
    {
        $company = $application->jobPosting->company->name;

        if ($event->to_stage !== null) {
            return __(':company moved your application to :stage', [
                'company' => $company,
                'stage' => ApplicationStage::tryFrom($event->to_stage)?->label() ?? $event->to_stage,
            ]);
        }

        if ($event->changed_by_id === $candidateUserId) {
            return __('You withdrew this application');
        }

        return __(':company marked this application as :outcome', [
            'company' => $company,
            'outcome' => ApplicationOutcomeStatus::tryFrom($event->to_outcome_status)?->label() ?? $event->to_outcome_status,
        ]);
    }
}
