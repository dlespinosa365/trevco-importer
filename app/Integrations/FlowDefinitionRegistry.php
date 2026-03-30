<?php

namespace App\Integrations;

use App\Integrations\Contracts\Step;
use FilesystemIterator;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use InvalidArgumentException;

final class FlowDefinitionRegistry
{
    public function __construct(
        private readonly string $integrationsPath,
    ) {}

    /**
     * @return list<string>
     */
    public function allFlowRefs(): array
    {
        $refs = [];
        $integrationsPath = $this->integrationsPath;

        if (! is_dir($integrationsPath)) {
            return [];
        }

        foreach (new FilesystemIterator($integrationsPath, FilesystemIterator::SKIP_DOTS) as $integrationDir) {
            if (! $integrationDir->isDir()) {
                continue;
            }

            $integrationSlug = $integrationDir->getFilename();
            $configPath = $integrationsPath.DIRECTORY_SEPARATOR.$integrationSlug.DIRECTORY_SEPARATOR.'config.php';

            if (! is_file($configPath)) {
                continue;
            }

            $groupsPath = $integrationsPath.DIRECTORY_SEPARATOR.$integrationSlug.DIRECTORY_SEPARATOR.'groups';

            if (! is_dir($groupsPath)) {
                continue;
            }

            foreach (new FilesystemIterator($groupsPath, FilesystemIterator::SKIP_DOTS) as $groupDir) {
                if (! $groupDir->isDir()) {
                    continue;
                }

                $groupSlug = $groupDir->getFilename();
                $groupConfig = $groupsPath.DIRECTORY_SEPARATOR.$groupSlug.DIRECTORY_SEPARATOR.'config.php';

                if (! is_file($groupConfig)) {
                    continue;
                }

                $flowsPath = $groupsPath.DIRECTORY_SEPARATOR.$groupSlug.DIRECTORY_SEPARATOR.'flows';

                if (! is_dir($flowsPath)) {
                    continue;
                }

                foreach (new FilesystemIterator($flowsPath, FilesystemIterator::SKIP_DOTS) as $flowDir) {
                    if (! $flowDir->isDir()) {
                        continue;
                    }

                    $flowSlug = $flowDir->getFilename();
                    $flowPhp = $flowsPath.DIRECTORY_SEPARATOR.$flowSlug.DIRECTORY_SEPARATOR.'flow.php';

                    if (is_file($flowPhp)) {
                        $refs[] = $integrationSlug.'/'.$groupSlug.'/'.$flowSlug;
                    }
                }
            }
        }

        sort($refs);

        return $refs;
    }

    /**
     * One row per integration folder that has a valid integrations/{slug}/config.php.
     *
     * @return list<array{slug: string, key: string, name: string, image_url: ?string}>
     */
    public function allIntegrations(): array
    {
        $integrationsPath = $this->integrationsPath;
        $out = [];

        if (! is_dir($integrationsPath)) {
            return [];
        }

        foreach (new FilesystemIterator($integrationsPath, FilesystemIterator::SKIP_DOTS) as $integrationDir) {
            if (! $integrationDir->isDir()) {
                continue;
            }

            $integrationSlug = $integrationDir->getFilename();
            $configPath = $integrationsPath.DIRECTORY_SEPARATOR.$integrationSlug.DIRECTORY_SEPARATOR.'config.php';

            if (! is_file($configPath)) {
                continue;
            }

            /** @var array<string, mixed> $config */
            $config = require $configPath;

            if (! isset($config['key']) || ! is_string($config['key']) || $config['key'] === '') {
                continue;
            }

            $name = isset($config['name']) && is_string($config['name']) && $config['name'] !== ''
                ? $config['name']
                : $integrationSlug;

            $imageUrl = isset($config['image_url']) && is_string($config['image_url']) && $config['image_url'] !== ''
                ? $config['image_url']
                : null;

            $out[] = [
                'slug' => $integrationSlug,
                'key' => $config['key'],
                'name' => $name,
                'image_url' => $imageUrl,
            ];
        }

        usort($out, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $out;
    }

    /**
     * @return array{groups: list<array{slug: string, flow_count: int}>, flow_count: int}
     */
    public function integrationGroupsWithFlowCounts(string $integrationSlug): array
    {
        $groupsPath = $this->integrationsPath.DIRECTORY_SEPARATOR.$integrationSlug.DIRECTORY_SEPARATOR.'groups';

        if (! is_dir($groupsPath)) {
            return ['groups' => [], 'flow_count' => 0];
        }

        $groups = [];
        $total = 0;

        foreach (new FilesystemIterator($groupsPath, FilesystemIterator::SKIP_DOTS) as $groupDir) {
            if (! $groupDir->isDir()) {
                continue;
            }

            $groupSlug = $groupDir->getFilename();
            $groupConfig = $groupsPath.DIRECTORY_SEPARATOR.$groupSlug.DIRECTORY_SEPARATOR.'config.php';

            if (! is_file($groupConfig)) {
                continue;
            }

            $flowsPath = $groupsPath.DIRECTORY_SEPARATOR.$groupSlug.DIRECTORY_SEPARATOR.'flows';
            $count = 0;

            if (is_dir($flowsPath)) {
                foreach (new FilesystemIterator($flowsPath, FilesystemIterator::SKIP_DOTS) as $flowDir) {
                    if (! $flowDir->isDir()) {
                        continue;
                    }

                    $flowSlug = $flowDir->getFilename();
                    $flowPhp = $flowsPath.DIRECTORY_SEPARATOR.$flowSlug.DIRECTORY_SEPARATOR.'flow.php';

                    if (is_file($flowPhp)) {
                        $count++;
                    }
                }
            }

            $groups[] = ['slug' => $groupSlug, 'flow_count' => $count];
            $total += $count;
        }

        usort($groups, fn (array $a, array $b): int => strcmp($a['slug'], $b['slug']));

        return ['groups' => $groups, 'flow_count' => $total];
    }

    public function resolve(string $flowRef): DiskFlowDefinition
    {
        $flowRef = trim($flowRef, '/');

        $parts = explode('/', $flowRef);

        if (count($parts) !== 3 || in_array('', $parts, true)) {
            throw new InvalidArgumentException("Invalid flow_ref \"{$flowRef}\": expected integration/group/flow.");
        }

        [$integrationSlug, $groupSlug, $flowSlug] = $parts;

        $integrationConfigPath = $this->integrationsPath.DIRECTORY_SEPARATOR.$integrationSlug.DIRECTORY_SEPARATOR.'config.php';
        $groupConfigPath = $this->integrationsPath.DIRECTORY_SEPARATOR.$integrationSlug.DIRECTORY_SEPARATOR.'groups'.DIRECTORY_SEPARATOR.$groupSlug.DIRECTORY_SEPARATOR.'config.php';
        $flowPath = $this->integrationsPath.DIRECTORY_SEPARATOR.$integrationSlug.DIRECTORY_SEPARATOR.'groups'.DIRECTORY_SEPARATOR.$groupSlug.DIRECTORY_SEPARATOR.'flows'.DIRECTORY_SEPARATOR.$flowSlug.DIRECTORY_SEPARATOR.'flow.php';

        foreach ([$integrationConfigPath => 'integration config', $groupConfigPath => 'group config', $flowPath => 'flow'] as $path => $label) {
            if (! is_file($path)) {
                throw new FileNotFoundException("Missing {$label} for flow_ref \"{$flowRef}\" at {$path}.");
            }
        }

        /** @var array<string, mixed> $integrationConfig */
        $integrationConfig = require $integrationConfigPath;

        /** @var array<string, mixed> $groupConfig */
        $groupConfig = require $groupConfigPath;

        /** @var array<string, mixed> $flowConfig */
        $flowConfig = require $flowPath;

        if (! isset($integrationConfig['key']) || ! is_string($integrationConfig['key']) || $integrationConfig['key'] === '') {
            throw new InvalidArgumentException("Integration config for \"{$integrationSlug}\" must define a non-empty string \"key\".");
        }

        if (! array_key_exists('entry', $flowConfig)) {
            throw new InvalidArgumentException("Flow \"{$flowRef}\" must define \"entry\" in flow.php.");
        }

        $rawEntry = $flowConfig['entry'];
        /** @var list<class-string> $entryClasses */
        $entryClasses = is_array($rawEntry) ? array_values($rawEntry) : [$rawEntry];

        if ($entryClasses === []) {
            throw new InvalidArgumentException("Flow \"{$flowRef}\" entry must contain at least one step class.");
        }

        foreach ($entryClasses as $entry) {
            if (! is_string($entry) || ! class_exists($entry)) {
                throw new InvalidArgumentException("Flow \"{$flowRef}\" entry must list existing class FQCNs.");
            }

            if (! is_subclass_of($entry, Step::class)) {
                throw new InvalidArgumentException('Flow "'.$flowRef.'" entry classes must implement '.Step::class.'.');
            }
        }

        $firstEntry = $entryClasses[0];
        $meta = FlowDefinitionMetadata::resolve($flowRef, $firstEntry);

        $groupExtra = $groupConfig['extra_config'] ?? [];
        $flowExtra = $meta['extra_config'];

        if (! is_array($groupExtra)) {
            throw new InvalidArgumentException("Group \"{$integrationSlug}/{$groupSlug}\" extra_config must be an array.");
        }

        if (! is_array($flowExtra)) {
            throw new InvalidArgumentException("Flow \"{$flowRef}\" extra_config from step metadata must be an array.");
        }

        $failureNotifications = $this->mergeFailureNotificationsFromFiles(
            $integrationConfig,
            $groupConfig,
            $flowConfig,
            $integrationSlug
        );

        return new DiskFlowDefinition(
            flowRef: $flowRef,
            integrationKey: $integrationConfig['key'],
            name: $meta['name'],
            isActive: $meta['is_active'],
            entryClasses: $entryClasses,
            integrationConfig: $integrationConfig,
            groupExtraConfig: $groupExtra,
            flowExtraConfig: $flowExtra,
            failureNotifications: $failureNotifications,
        );
    }

    /**
     * @param  array<string, mixed>  $integrationConfig
     * @param  array<string, mixed>  $groupConfig
     * @param  array<string, mixed>  $flowConfig
     * @return array<string, mixed>
     */
    private function mergeFailureNotificationsFromFiles(
        array $integrationConfig,
        array $groupConfig,
        array $flowConfig,
        string $integrationSlug,
    ): array {
        $base = $integrationConfig['failure_notifications'] ?? [];

        if (! is_array($base)) {
            throw new InvalidArgumentException("Integration \"{$integrationSlug}\" failure_notifications must be an array when present.");
        }

        $merged = $base;

        $groupFn = $groupConfig['failure_notifications'] ?? null;
        if (is_array($groupFn)) {
            $merged = $this->overlayFailureNotificationChannels($merged, $groupFn);
        }

        $flowFn = $flowConfig['failure_notifications'] ?? null;
        if (is_array($flowFn)) {
            $merged = $this->overlayFailureNotificationChannels($merged, $flowFn);
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $overlay
     * @return array<string, mixed>
     */
    private function overlayFailureNotificationChannels(array $base, array $overlay): array
    {
        foreach (['mail', 'slack_webhook_url', 'teams_workflow_webhook_url'] as $key) {
            if (! array_key_exists($key, $overlay)) {
                continue;
            }

            $val = $overlay[$key];

            if ($key === 'mail') {
                if (is_array($val)) {
                    $base[$key] = array_values(array_filter(array_map(
                        fn ($v) => is_string($v) && $v !== '' ? $v : null,
                        $val
                    )));
                }

                continue;
            }

            if ($val === null || (is_string($val) && $val === '')) {
                $base[$key] = null;
            } elseif (is_string($val)) {
                $base[$key] = $val;
            }
        }

        return $base;
    }
}
