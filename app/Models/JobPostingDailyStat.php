<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['job_posting_id', 'date', 'views'])]
class JobPostingDailyStat extends Model
{
    public $timestamps = false;

    /**
     * Stored as a bare day, the same text RecordJobView's upsert writes.
     * Without this the model would write "2026-10-05 00:00:00", which
     * SQLite keeps as a different value from "2026-10-05": the unique
     * pair would let one day have two rows, and grouping by day would
     * split it in two.
     */
    protected $dateFormat = 'Y-m-d';

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
