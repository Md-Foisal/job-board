<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\AccountStatus;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'everyone' => Tab::make('Everyone'),
            'suspended' => Tab::make('Suspended')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('account_status', '!=', AccountStatus::Active->value)),
            'staff' => Tab::make('Staff')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('staff_role')),
        ];
    }
}
