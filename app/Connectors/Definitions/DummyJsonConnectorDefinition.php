<?php

namespace App\Connectors\Definitions;

use App\Connectors\ConnectorFieldSpec;
use App\Connectors\ConnectorTypeDefinition;
use App\Enums\ConnectorType;

final class DummyJsonConnectorDefinition implements ConnectorTypeDefinition
{
    public function type(): ConnectorType
    {
        return ConnectorType::DummyJson;
    }

    public function fields(): array
    {
        return [
            new ConnectorFieldSpec(
                name: 'base_url',
                label: 'Base URL',
                type: 'text',
                rules: ['required', 'string', 'max:255'],
                helperText: 'e.g. https://dummyjson.com — client method getOrders() calls GET {base_url}/carts.',
            ),
        ];
    }

    public function secretFieldNames(): array
    {
        return [];
    }
}
