<?php

namespace App\Actions;

use App\Enums\ModerationAction;
use App\Models\Company;
use App\Models\ModerationEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Marking a company as checked. The badge candidates see rests on this,
 * so it is only ever set by a person, and the trail says which one.
 */
class VerifyCompany
{
    public function __invoke(Company $company, User $staff): ModerationEvent
    {
        return DB::transaction(function () use ($company, $staff) {
            $company->verified_at = now();
            $company->save();

            return $company->moderationEvents()->create([
                'admin_id' => $staff->id,
                'action' => ModerationAction::VerifyCompany,
            ]);
        });
    }
}
