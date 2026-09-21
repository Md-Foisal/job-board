<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Enums\AccountStatus;
use App\Filament\Resources\Companies\CompanyResource;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageCompanies extends ManageRecords
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'unverified' => Tab::make('Not verified')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNull('verified_at')
                    ->where('account_status', AccountStatus::Active->value)),
            'verified' => Tab::make('Verified')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNotNull('verified_at')
                    ->where('account_status', AccountStatus::Active->value)),
            'banned' => Tab::make('Banned')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('account_status', '!=', AccountStatus::Active->value)),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'unverified';
    }
}
