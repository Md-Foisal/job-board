<?php

namespace App\Models;

use App\Enums\LanguageLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['candidate_profile_id', 'name', 'level'])]
class CandidateLanguage extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'level' => LanguageLevel::class,
        ];
    }

    public function candidateProfile()
    {
        return $this->belongsTo(CandidateProfile::class);
    }
}
