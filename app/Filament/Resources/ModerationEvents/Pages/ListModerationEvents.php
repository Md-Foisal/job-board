<?php

namespace App\Filament\Resources\ModerationEvents\Pages;

use App\Filament\Resources\ModerationEvents\ModerationEventResource;
use Filament\Resources\Pages\ManageRecords;

class ListModerationEvents extends ManageRecords
{
    protected static string $resource = ModerationEventResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
