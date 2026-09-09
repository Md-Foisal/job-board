<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'headline', 'bio', 'portfolio_url', 'github_url', 'linkedin_url'])]
class CandidateProfile extends Model
{
    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class);
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
            ->using(\App\Models\Pivots\CandidateProfileSkillPivot::class);
    }
}
