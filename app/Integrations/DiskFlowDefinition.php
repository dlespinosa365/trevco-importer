<?php

namespace App\Integrations;

use App\Integrations\Contracts\Step;

final class DiskFlowDefinition
{
    /**
     * @param  list<class-string<Step>>  $entryClasses
     * @param  array<string, mixed>  $integrationConfig  Full array from integrations/{I}/config.php
     * @param  array<string, mixed>  $groupExtraConfig
     * @param  array<string, mixed>  $flowExtraConfig  From first entry DefinesFlowMetadata when present
     * @param  array<string, mixed>  $failureNotifications  Merged from integration, group, and flow disk configs
     */
    public function __construct(
        public string $flowRef,
        public string $integrationKey,
        public string $name,
        public bool $isActive,
        public array $entryClasses,
        public array $integrationConfig,
        public array $groupExtraConfig,
        public array $flowExtraConfig,
        public array $failureNotifications,
    ) {}

    /**
     * @return class-string<Step>
     */
    public function firstEntry(): string
    {
        return $this->entryClasses[0];
    }

    /**
     * @return array<string, mixed>
     */
    public function mergedConfig(): array
    {
        $integrationExtra = $this->integrationConfig['extra_config'] ?? [];

        return array_replace_recursive(
            is_array($integrationExtra) ? $integrationExtra : [],
            $this->groupExtraConfig,
            $this->flowExtraConfig
        );
    }
}
