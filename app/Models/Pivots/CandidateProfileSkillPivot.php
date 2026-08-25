<?php

namespace App\Models\Pivots;

use App\Enums\ProficiencyLevel;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CandidateProfileSkillPivot extends Pivot
{
    protected function casts(): array
    {
        return [
            'proficiency' => ProficiencyLevel::class,
        ];
    }
}
