<?php

namespace App\Policies;

use App\Models\CompanyReview;
use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    /**
     * Acting on a report -- dismissing it, or taking it as grounds to act
     * on its subject.
     *
     * A report is about a job posting, a company or a company review, and
     * each traces back to one company; staff recuse themselves from reports
     * that reach their own employer. A report whose subject has since
     * disappeared has no company to be compromised by, so it stays
     * actionable. Nobody rules on reports about a review they wrote.
     */
    public function moderate(User $user, Report $report): bool
    {
        if (! $user->isActiveStaff()) {
            return false;
        }

        if ($report->reportable instanceof CompanyReview && $report->reportable->isWrittenBy($user)) {
            return false;
        }

        $company = $report->subjectCompany();

        return $company === null
            || ! $user->worksAt($company);
    }
}
