<?php

namespace App\Filament\Resources\Connectors\Pages;

use App\Connectors\ConnectorCredentialsNormalizer;
use App\Enums\ConnectorType;
use App\Filament\Resources\Connectors\ConnectorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateConnector extends CreateRecord
{
    protected static string $resource = ConnectorResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $type = $data['connector_type'] instanceof ConnectorType
            ? $data['connector_type']
            : ConnectorType::from((string) $data['connector_type']);

        $data['connector_type'] = $type;
        $data['credentials'] = ConnectorCredentialsNormalizer::normalize($type, $data['credentials'] ?? []);
        $data['user_id'] = null;

        return $data;
    }
}
