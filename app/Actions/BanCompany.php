<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Enums\ModerationAction;
use App\Enums\ReportStatus;
use App\Models\Company;
use App\Models\ModerationEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Barring a company from the platform. Its postings leave the public
 * site at once without being touched -- every public listing already
 * filters out suspended companies -- so lifting the ban brings them back
 * exactly as they were. Open reports against the company are closed as
 * actioned: this is the action they asked for.
 */
class BanCompany
{
    public function __invoke(Company $company, User $staff, string $reason): ModerationEvent
    {
        return DB::transaction(function () use ($company, $staff, $reason) {
            $company->account_status = AccountStatus::Suspended;
            $company->save();

            $company->reports()
                ->where('review_status', ReportStatus::Pending->value)
                ->update(['review_status' => ReportStatus::Actioned->value]);

            return $company->moderationEvents()->create([
                'admin_id' => $staff->id,
                'action' => ModerationAction::BanCompany,
                'reason' => $reason,
            ]);
        });
    }
}
