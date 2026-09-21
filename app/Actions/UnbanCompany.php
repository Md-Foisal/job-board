<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Enums\ModerationAction;
use App\Models\Company;
use App\Models\ModerationEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Lifting a ban. The company's postings reappear as they were; nothing
 * was deleted when it was banned.
 */
class UnbanCompany
{
    public function __invoke(Company $company, User $staff): ModerationEvent
    {
        return DB::transaction(function () use ($company, $staff) {
            $company->account_status = AccountStatus::Active;
            $company->save();

            return $company->moderationEvents()->create([
                'admin_id' => $staff->id,
                'action' => ModerationAction::UnbanCompany,
            ]);
        });
    }
}
