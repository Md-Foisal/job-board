<?php

namespace App\Filament\Resources\Skills\Pages;

use App\Filament\Resources\Skills\SkillResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageSkills extends ManageRecords
{
    protected static string $resource = SkillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // One verb from button to modal to submit; Filament's own default
            // mixes "Add", "Create" and "Create & create another".
            CreateAction::make()
                ->label('Add skill')
                ->modalHeading('Add a skill')
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
