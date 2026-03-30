<?php

namespace App\Connectors\Definitions;

use App\Connectors\ConnectorFieldSpec;
use App\Connectors\ConnectorTypeDefinition;
use App\Enums\ConnectorType;

final class NetSuiteConnectorDefinition implements ConnectorTypeDefinition
{
    public function type(): ConnectorType
    {
        return ConnectorType::NetSuite;
    }

    public function fields(): array
    {
        return [
            new ConnectorFieldSpec(
                name: 'account_id',
                label: 'Account ID',
                type: 'text',
                rules: ['required', 'string', 'max:255'],
                helperText: 'As in NetSuite (e.g. 3521821_SB1). The REST host uses hyphens and lowercase (3521821-sb1); OAuth realm keeps this value.',
            ),
            new ConnectorFieldSpec(
                name: 'client_id',
                label: 'Client ID (Consumer Key)',
                type: 'text',
                rules: ['required', 'string', 'max:512'],
                helperText: 'From the integration record in NetSuite (Consumer Key).',
            ),
            new ConnectorFieldSpec(
                name: 'client_secret',
                label: 'Client secret (Consumer Secret)',
                type: 'password',
                rules: ['required', 'string', 'max:2048'],
                secret: true,
            ),
            new ConnectorFieldSpec(
                name: 'token_id',
                label: 'Token ID',
                type: 'text',
                rules: ['required', 'string', 'max:512'],
                helperText: 'User access token ID from NetSuite TBA setup.',
            ),
            new ConnectorFieldSpec(
                name: 'token_secret',
                label: 'Token secret',
                type: 'password',
                rules: ['required', 'string', 'max:2048'],
                secret: true,
            ),
            new ConnectorFieldSpec(
                name: 'restlet_url',
                label: 'RESTlet URL',
                type: 'text',
                rules: ['required', 'string', 'max:2048'],
                helperText: 'Full RESTlet endpoint URL, for example: https://<account>.restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=...&deploy=...',
            ),
        ];
    }

    public function secretFieldNames(): array
    {
        return ['client_secret', 'token_secret'];
    }
}
