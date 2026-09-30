<?php

namespace App\Models;

use App\Enums\AiFeature;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id', 'company_id', 'feature', 'provider', 'model',
    'input_tokens', 'output_tokens', 'cost_micro_usd',
])]
class AiUsage extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'feature' => AiFeature::class,
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cost_micro_usd' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
