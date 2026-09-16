<?php

namespace App\Enums;

/**
 * Every decision a staff member can record against a subject.
 *
 * Reversals (unban, reinstate, revoke verification) are first-class cases
 * rather than an absence: a moderation mistake has to be correctable, and
 * the correction has to appear in the trail too.
 *
 * Automatic hiding after repeated reports is not here -- that is the
 * platform reacting, not a person deciding.
 */
enum ModerationAction: string
{
    case ApproveJobPosting = 'approve_job_posting';
    case RejectJobPosting = 'reject_job_posting';

    case DismissReports = 'dismiss_reports';

    case VerifyCompany = 'verify_company';
    case RevokeCompanyVerification = 'revoke_company_verification';
    case RequestCompanyDocuments = 'request_company_documents';
    case BanCompany = 'ban_company';
    case UnbanCompany = 'unban_company';

    case SuspendUser = 'suspend_user';
    case ReinstateUser = 'reinstate_user';

    public function label(): string
    {
        return match ($this) {
            self::ApproveJobPosting => 'Approved job posting',
            self::RejectJobPosting => 'Rejected job posting',
            self::DismissReports => 'Dismissed reports',
            self::VerifyCompany => 'Verified company',
            self::RevokeCompanyVerification => 'Revoked company verification',
            self::RequestCompanyDocuments => 'Requested company documents',
            self::BanCompany => 'Banned company',
            self::UnbanCompany => 'Lifted company ban',
            self::SuspendUser => 'Suspended user',
            self::ReinstateUser => 'Reinstated user',
        };
    }

    /**
     * Whether this action requires the staff member to write a reason.
     * Anything that takes something away from someone has to say why;
     * restoring access does not.
     */
    public function requiresReason(): bool
    {
        return in_array($this, [
            self::RejectJobPosting,
            self::RevokeCompanyVerification,
            self::RequestCompanyDocuments,
            self::BanCompany,
            self::SuspendUser,
        ], true);
    }
}
