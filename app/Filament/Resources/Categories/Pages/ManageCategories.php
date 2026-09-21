<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageCategories extends ManageRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // One verb from button to modal to submit; Filament's own default
            // mixes "Add", "Create" and "Create & create another".
            CreateAction::make()
                ->label('Add category')
                ->modalHeading('Add a category')
                ->modalSubmitActionLabel('Add')
                ->createAnotherAction(fn ($action) => $action->label('Add and start another')),
        ];
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('In use')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('deleted_at')),
            'removed' => Tab::make('Removed')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('deleted_at')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'active';
    }
}
