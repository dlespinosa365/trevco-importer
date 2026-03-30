<?php

namespace App\Integrations;

use App\Integrations\Contracts\DefinesFlowMetadata;
use App\Integrations\Contracts\Step;
use Illuminate\Support\Str;

final class FlowDefinitionMetadata
{
    /**
     * @param  class-string<Step>  $firstEntryClass
     * @return array{name: string, is_active: bool, extra_config: array<string, mixed>}
     */
    public static function resolve(string $flowRef, string $firstEntryClass): array
    {
        if (is_subclass_of($firstEntryClass, DefinesFlowMetadata::class)) {
            return [
                'name' => $firstEntryClass::flowDefinitionName(),
                'is_active' => $firstEntryClass::flowDefinitionIsActive(),
                'extra_config' => $firstEntryClass::flowDefinitionExtraConfig(),
            ];
        }

        return [
            'name' => Str::title(str_replace(['/', '-'], [' ', ' '], $flowRef)),
            'is_active' => true,
            'extra_config' => [],
        ];
    }
}
