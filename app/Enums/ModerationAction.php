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

    case ApproveCompanyReview = 'approve_company_review';
    case RejectCompanyReview = 'reject_company_review';
    case ApproveReviewResponse = 'approve_review_response';
    case RejectReviewResponse = 'reject_review_response';

    case DismissReports = 'dismiss_reports';

    case VerifyCompany = 'verify_company';
    case RevokeCompanyVerification = 'revoke_company_verification';
    case RequestCompanyDocuments = 'request_company_documents';
    case BanCompany = 'ban_company';
    case UnbanCompany = 'unban_company';

    case SuspendUser = 'suspend_user';
    case ReinstateUser = 'reinstate_user';
    case EraseUser = 'erase_user';

    public function label(): string
    {
        return match ($this) {
            self::ApproveJobPosting => 'Approved job posting',
            self::RejectJobPosting => 'Rejected job posting',
            self::ApproveCompanyReview => 'Approved company review',
            self::RejectCompanyReview => 'Rejected company review',
            self::ApproveReviewResponse => 'Approved company response to a review',
            self::RejectReviewResponse => 'Rejected company response to a review',
            self::DismissReports => 'Dismissed reports',
            self::VerifyCompany => 'Verified company',
            self::RevokeCompanyVerification => 'Revoked company verification',
            self::RequestCompanyDocuments => 'Requested company documents',
            self::BanCompany => 'Banned company',
            self::UnbanCompany => 'Lifted company ban',
            self::SuspendUser => 'Suspended user',
            self::ReinstateUser => 'Reinstated user',
            self::EraseUser => 'Erased personal data',
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
            self::RejectCompanyReview,
            self::RejectReviewResponse,
            self::RevokeCompanyVerification,
            self::RequestCompanyDocuments,
            self::BanCompany,
            self::SuspendUser,
            self::EraseUser,
        ], true);
    }
}
