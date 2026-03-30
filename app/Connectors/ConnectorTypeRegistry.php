<?php

namespace App\Connectors;

use App\Connectors\Definitions\AmazonVendorCentralConnectorDefinition;
use App\Connectors\Definitions\DummyJsonConnectorDefinition;
use App\Connectors\Definitions\NetSuiteConnectorDefinition;
use App\Enums\ConnectorType;
use InvalidArgumentException;

final class ConnectorTypeRegistry
{
    /**
     * @return list<ConnectorTypeDefinition>
     */
    public static function all(): array
    {
        return [
            new NetSuiteConnectorDefinition,
            new AmazonVendorCentralConnectorDefinition,
            new DummyJsonConnectorDefinition,
        ];
    }

    public static function definition(ConnectorType $type): ConnectorTypeDefinition
    {
        foreach (self::all() as $definition) {
            if ($definition->type() === $type) {
                return $definition;
            }
        }

        throw new InvalidArgumentException('Unknown connector type: '.$type->value);
    }
}
