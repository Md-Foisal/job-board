<?php

namespace App\Filament\Resources\CompanyReviews\Pages;

use App\Enums\ModerationStatus;
use App\Filament\Resources\CompanyReviews\CompanyReviewResource;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageCompanyReviews extends ManageRecords
{
    protected static string $resource = CompanyReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Waiting is the queue. Approved and Rejected are there so a decision
     * can be found again and reversed. Responses is the second queue: the
     * companies' answers waiting, whatever state their review is in.
     */
    public function getTabs(): array
    {
        return collect([
            ModerationStatus::Pending->value => 'Waiting',
            ModerationStatus::Approved->value => ModerationStatus::Approved->label(),
            ModerationStatus::Rejected->value => ModerationStatus::Rejected->label(),
        ])->map(fn (string $label, string $status) => Tab::make($label)
            ->modifyQueryUsing(fn (Builder $query) => $query->where('moderation_status', $status)))
            ->put('responses', Tab::make('Responses')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('response_status', ModerationStatus::Pending->value)))
            ->all();
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return ModerationStatus::Pending->value;
    }
}
