<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\ApplicationStatus;


#[Fillable(['cover_letter', 'resume', 'status', 'job_posting_id'])]
class Application extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
        ];
    }

    public function jobPosting()
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
