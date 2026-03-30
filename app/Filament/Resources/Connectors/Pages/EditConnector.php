<?php

namespace App\Filament\Resources\Connectors\Pages;

use App\Connectors\ConnectorCredentialsNormalizer;
use App\Connectors\ConnectorTypeRegistry;
use App\Enums\ConnectorType;
use App\Filament\Resources\Connectors\ConnectorResource;
use App\Models\Connector;
use Filament\Resources\Pages\EditRecord;

class EditConnector extends EditRecord
{
    protected static string $resource = ConnectorResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = parent::mutateFormDataBeforeFill($data);

        /** @var Connector $record */
        $record = $this->record;
        $type = $record->connector_type;
        if (! $type instanceof ConnectorType) {
            return $data;
        }

        $definition = ConnectorTypeRegistry::definition($type);
        $creds = $data['credentials'] ?? [];
        if (! is_array($creds)) {
            $creds = [];
        }
        foreach ($definition->secretFieldNames() as $name) {
            unset($creds[$name]);
        }
        $data['credentials'] = $creds;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = parent::mutateFormDataBeforeSave($data);

        /** @var Connector $record */
        $record = $this->record;
        $type = $record->connector_type;
        if (! $type instanceof ConnectorType) {
            return $data;
        }

        $incoming = $data['credentials'] ?? [];
        if (! is_array($incoming)) {
            $incoming = [];
        }

        $old = $record->credentials ?? [];
        if (! is_array($old)) {
            $old = [];
        }

        foreach (ConnectorTypeRegistry::definition($type)->secretFieldNames() as $name) {
            if (! isset($incoming[$name]) || $incoming[$name] === '' || $incoming[$name] === null) {
                $incoming[$name] = $old[$name] ?? null;
            }
        }

        $data['credentials'] = ConnectorCredentialsNormalizer::normalize($type, $incoming);

        return $data;
    }
}
