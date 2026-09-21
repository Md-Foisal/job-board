<?php

namespace App\Models;

use App\Models\Pivots\CandidateProfileSkillPivot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'headline', 'bio', 'cover_photo_path', 'portfolio_url', 'github_url', 'linkedin_url'])]
class CandidateProfile extends Model
{
    use HasFactory;

    /**
     * Including deleted accounts: a profile outlives its owner's deletion
     * (applications point at it), and every page that shows an applicant
     * reads the name through here -- "Deleted user" once erased, their own
     * name while the account can still be restored.
     */
    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function preference()
    {
        return $this->hasOne(CandidatePreference::class);
    }

    public function educationRecords()
    {
        return $this->hasMany(EducationRecord::class);
    }

    public function experienceRecords()
    {
        return $this->hasMany(ExperienceRecord::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class)
            ->withPivot('proficiency')
            ->using(CandidateProfileSkillPivot::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }
}
