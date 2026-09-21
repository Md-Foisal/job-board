<?php

namespace App\Filament\Resources\JobPostings\Pages;

use App\Enums\ModerationStatus;
use App\Filament\Resources\JobPostings\JobPostingResource;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageJobPostings extends ManageRecords
{
    protected static string $resource = JobPostingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Waiting is the queue itself: pending and still open. The last tab
     * keeps pending postings their company closed, or that lapsed, out of
     * the way but findable. Approved and Rejected are there so a decision
     * can be found again and reversed.
     */
    public function getTabs(): array
    {
        return [
            ModerationStatus::Pending->value => Tab::make('Waiting')
                ->modifyQueryUsing(fn (Builder $query) => $query->awaitingReview()),
            ModerationStatus::Approved->value => Tab::make(ModerationStatus::Approved->label())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('moderation_status', ModerationStatus::Approved->value)),
            ModerationStatus::Rejected->value => Tab::make(ModerationStatus::Rejected->label())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('moderation_status', ModerationStatus::Rejected->value)),
            'not_open' => Tab::make('Waiting, not open')
                ->modifyQueryUsing(fn (Builder $query) => $query->awaitingReviewButNotOpen()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return ModerationStatus::Pending->value;
    }
}
