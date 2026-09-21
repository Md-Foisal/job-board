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
     * Pending is the queue itself. The other two are there so a decision
     * can be found again and reversed.
     */
    public function getTabs(): array
    {
        return collect(ModerationStatus::cases())
            ->mapWithKeys(fn (ModerationStatus $status) => [
                $status->value => Tab::make($status === ModerationStatus::Pending ? 'Waiting' : $status->label())
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('moderation_status', $status->value)),
            ])
            ->all();
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return ModerationStatus::Pending->value;
    }
}
