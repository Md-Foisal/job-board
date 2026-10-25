<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A cover letter written in the builder, aimed at one job (see the
 * migration).
 */
#[Fillable(['candidate_profile_id', 'job_posting_id', 'cv_version_id', 'company_name', 'role_title', 'recipient', 'body'])]
class CoverLetter extends Model
{
    use HasFactory;

    /**
     * Under the application form's 5,000 characters, which counts the
     * paragraph tags the letter gains when it fills that form.
     */
    public const BODY_MAX = 4500;

    public const MAX_PER_PROFILE = 20;

    public function candidateProfile()
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    public function jobPosting()
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function cvVersion()
    {
        return $this->belongsTo(CvVersion::class);
    }

    /**
     * The company the letter is addressed to: the job's own, or the one
     * typed in.
     */
    public function companyName(): ?string
    {
        return $this->jobPosting?->company?->name ?? $this->company_name;
    }

    public function roleTitle(): ?string
    {
        return $this->jobPosting?->title ?? $this->role_title;
    }
}
