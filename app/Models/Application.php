<?php

namespace App\Models;

use App\Enums\ApplicationOutcomeStatus;
use App\Enums\ApplicationStage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'job_posting_id', 'candidate_profile_id', 'resume_document_id',
    'cover_letter', 'outcome_status', 'stage',
])]
class Application extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'outcome_status' => ApplicationOutcomeStatus::class,
            'stage' => ApplicationStage::class,
        ];
    }

    public function jobPosting()
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function candidateProfile()
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    public function resumeDocument()
    {
        return $this->belongsTo(Document::class, 'resume_document_id');
    }

    public function screeningAnswers()
    {
        return $this->hasMany(ScreeningAnswer::class);
    }

    public function events()
    {
        return $this->hasMany(ApplicationEvent::class)->latest('created_at');
    }
}
