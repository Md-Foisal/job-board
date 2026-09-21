<?php

namespace App\Actions;

use App\Enums\ModerationAction;
use App\Models\Company;
use App\Models\ModerationEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Taking the verified badge away, with the reason on record.
 */
class RevokeCompanyVerification
{
    public function __invoke(Company $company, User $staff, string $reason): ModerationEvent
    {
        return DB::transaction(function () use ($company, $staff, $reason) {
            $company->verified_at = null;
            $company->save();

            return $company->moderationEvents()->create([
                'admin_id' => $staff->id,
                'action' => ModerationAction::RevokeCompanyVerification,
                'reason' => $reason,
            ]);
        });
    }
}
