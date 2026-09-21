<?php

namespace App\Actions;

use App\Enums\ModerationAction;
use App\Enums\ReportStatus;
use App\Models\ModerationEvent;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Closing every open report against one subject as not upheld, with one
 * event for the decision.
 *
 * It takes a report rather than the subject so that reports about
 * something no longer there can still be cleared: the subject's type and
 * id are all it needs, and a report always carries both.
 */
class DismissReports
{
    public function __invoke(Report $report, User $staff, ?string $note = null): ModerationEvent
    {
        return DB::transaction(function () use ($report, $staff, $note) {
            Report::query()
                ->where('reportable_type', $report->reportable_type)
                ->where('reportable_id', $report->reportable_id)
                ->where('review_status', ReportStatus::Pending->value)
                ->update(['review_status' => ReportStatus::Reviewed->value]);

            return ModerationEvent::create([
                'admin_id' => $staff->id,
                'subject_type' => $report->reportable_type,
                'subject_id' => $report->reportable_id,
                'action' => ModerationAction::DismissReports,
                'reason' => $note,
            ]);
        });
    }
}
