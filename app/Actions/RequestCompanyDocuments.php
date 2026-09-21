<?php

namespace App\Actions;

use App\Enums\ModerationAction;
use App\Models\Company;
use App\Models\ModerationEvent;
use App\Models\User;
use App\Notifications\CompanyDocumentsRequested;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Asking a company to prove itself before it can be verified. Nothing
 * about the company changes; the request -- what was asked for -- is the
 * record, and it is what tells the next reviewer the case is waiting on
 * the company rather than on staff.
 */
class RequestCompanyDocuments
{
    public function __invoke(Company $company, User $staff, string $reason): ModerationEvent
    {
        $event = DB::transaction(function () use ($company, $staff, $reason) {
            return $company->moderationEvents()->create([
                'admin_id' => $staff->id,
                'action' => ModerationAction::RequestCompanyDocuments,
                'reason' => $reason,
            ]);
        });

        Notification::send($company->decisionMakers(), new CompanyDocumentsRequested($company, $reason));

        return $event;
    }
}
