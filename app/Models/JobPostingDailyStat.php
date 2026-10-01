<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['job_posting_id', 'date', 'views'])]
class JobPostingDailyStat extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'views' => 'integer',
        ];
    }

    public function jobPosting()
    {
        return $this->belongsTo(JobPosting::class);
    }
}
