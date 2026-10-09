<?php

namespace App\Models;

use App\Enums\ContactTopic;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'name', 'email', 'topic', 'body'])]
class ContactMessage extends Model
{
    use MassPrunable;

    /**
     * What the contact page promises. Working days, because one team
     * answers and nobody watches the inbox at the weekend.
     */
    public const REPLY_WITHIN_WORKING_DAYS = 2;

    /**
     * How long a closed message is kept, for the case where the same
     * person writes back about it, before it is deleted for good.
     */
    public const KEPT_AFTER_CLOSING_MONTHS = 12;

    protected function casts(): array
    {
        return [
            'topic' => ContactTopic::class,
            'closed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by_id');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    public function status(): string
    {
        return match (true) {
            $this->isOpen() => 'Open',
            filled($this->reply_body) => 'Replied',
            default => 'Closed',
        };
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    public function prunable(): Builder
    {
        return static::query()
            ->whereNotNull('closed_at')
            ->where('closed_at', '<=', now()->subMonths(self::KEPT_AFTER_CLOSING_MONTHS));
    }
}
