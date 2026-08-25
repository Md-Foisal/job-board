<?php

namespace App\Models\Pivots;

use App\Enums\SkillImportance;
use Illuminate\Database\Eloquent\Relations\Pivot;

class JobListingSkillPivot extends Pivot
{
    protected function casts(): array
    {
        return [
            'importance' => SkillImportance::class,
        ];
    }
}
