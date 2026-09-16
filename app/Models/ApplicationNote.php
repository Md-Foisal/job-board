<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A hiring-side comment on one application. Private to the company:
 * the candidate never sees these, which is what separates a note from
 * an ApplicationEvent (the visible, factual stage history).
 */
#[Fillable(['application_id', 'author_id', 'note'])]
class ApplicationNote extends Model
{
    use HasFactory;

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
