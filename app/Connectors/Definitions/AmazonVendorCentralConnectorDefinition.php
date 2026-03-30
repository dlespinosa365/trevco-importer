<?php

namespace App\Connectors\Definitions;

use App\Connectors\ConnectorFieldSpec;
use App\Connectors\ConnectorTypeDefinition;
use App\Enums\ConnectorType;

final class AmazonVendorCentralConnectorDefinition implements ConnectorTypeDefinition
{
    public function type(): ConnectorType
    {
        return ConnectorType::AmazonVendorCentral;
    }

    public function fields(): array
    {
        return [
            new ConnectorFieldSpec(
                name: 'client_id',
                label: 'Client ID',
                type: 'text',
                rules: ['required', 'string', 'max:512'],
                helperText: 'LWA / SP-API application client identifier.',
            ),
            new ConnectorFieldSpec(
                name: 'client_secret',
                label: 'Client secret',
                type: 'password',
                rules: ['required', 'string', 'max:2048'],
                secret: true,
            ),
            new ConnectorFieldSpec(
                name: 'refresh_token',
                label: 'Refresh token',
                type: 'password',
                rules: ['required', 'string', 'max:4096'],
                helperText: 'Long-lived refresh token for Vendor Central / SP-API.',
                secret: true,
            ),
            new ConnectorFieldSpec(
                name: 'aws_access_key_id',
                label: 'AWS access key ID',
                type: 'text',
                rules: ['required', 'string', 'max:255'],
            ),
            new ConnectorFieldSpec(
                name: 'aws_secret_access_key',
                label: 'AWS secret access key',
                type: 'password',
                rules: ['required', 'string', 'max:2048'],
                secret: true,
            ),
            new ConnectorFieldSpec(
                name: 'region',
                label: 'AWS signing region',
                type: 'text',
                rules: ['required', 'string', 'max:64'],
                helperText: 'AWS region used for SigV4 (e.g. eu-west-1, us-east-1). Must match your SP-API deployment.',
            ),
            new ConnectorFieldSpec(
                name: 'sp_api_region',
                label: 'SP-API endpoint',
                type: 'text',
                rules: ['required', 'string', 'in:na,eu,fe'],
                helperText: 'na = North America, eu = Europe, fe = Far East (hostname sellingpartnerapi-{na|eu|fe}.amazon.com).',
            ),
        ];
    }

    public function secretFieldNames(): array
    {
        return ['client_secret', 'refresh_token', 'aws_secret_access_key'];
    }
}
