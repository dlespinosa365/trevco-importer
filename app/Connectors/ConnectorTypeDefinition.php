<?php

namespace App\Connectors;

use App\Enums\ConnectorType;

interface ConnectorTypeDefinition
{
    public function type(): ConnectorType;

    /**
     * @return list<ConnectorFieldSpec>
     */
    public function fields(): array;

    /**
     * @return list<string>
     */
    public function secretFieldNames(): array;
}
