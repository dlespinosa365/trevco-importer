<?php

namespace App\Filament\Resources\Connectors\Pages;

use App\Filament\Resources\Connectors\ConnectorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConnectors extends ListRecords
{
    protected static string $resource = ConnectorResource::class;

    /**
     * @return array<CreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
