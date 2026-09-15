<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['candidate_profile_id', 'document_type', 'file_path', 'original_filename'])]
class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
        ];
    }

    public function candidateProfile()
    {
        return $this->belongsTo(CandidateProfile::class);
    }
}
