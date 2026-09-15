<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['application_id', 'screening_question_id', 'answer_text'])]
class ScreeningAnswer extends Model
{
    const UPDATED_AT = null;

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function screeningQuestion()
    {
        return $this->belongsTo(ScreeningQuestion::class);
    }
}
