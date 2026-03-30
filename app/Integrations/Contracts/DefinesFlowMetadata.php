<?php

namespace App\Integrations\Contracts;

/**
 * Implemented by the first class in a flow's `entry` list to provide flow-level settings.
 */
interface DefinesFlowMetadata
{
    public static function flowDefinitionName(): string;

    public static function flowDefinitionIsActive(): bool;

    /**
     * @return array<string, mixed>
     */
    public static function flowDefinitionExtraConfig(): array;
}
