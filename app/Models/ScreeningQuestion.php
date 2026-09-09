<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['job_posting_id', 'question_text', 'display_order'])]
class ScreeningQuestion extends Model
{
    public function jobPosting()
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function answers()
    {
        return $this->hasMany(ScreeningAnswer::class);
    }
}
