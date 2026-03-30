<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Loads classes under integrations/{integration-slug}/groups/{group-kebab}/flows/{flow-kebab}/ matching namespace
 * Integrations\{IntegrationStudly}\Groups\{GroupStudly}\Flows\{FlowStudly}\{ShortClass}.
 * Group and flow directory names use Str::kebab() on each Studly segment (hyphens), same as integrations:make.
 */
final class IntegrationsAutoload
{
    public static function register(string $integrationsPath): void
    {
        $integrationsPath = rtrim($integrationsPath, DIRECTORY_SEPARATOR);

        spl_autoload_register(static function (string $class) use ($integrationsPath): void {
            if (! str_starts_with($class, 'Integrations\\')) {
                return;
            }

            $parts = explode('\\', $class);
            if (count($parts) < 7) {
                return;
            }

            if ($parts[0] !== 'Integrations') {
                return;
            }

            $integrationStudly = $parts[1];
            if (strcasecmp($parts[2], 'Groups') !== 0) {
                return;
            }

            $groupStudly = $parts[3];
            if (strcasecmp($parts[4], 'Flows') !== 0) {
                return;
            }

            $flowStudly = $parts[5];
            $shortClass = $parts[6];
            if ($shortClass === '' || count($parts) > 7) {
                return;
            }

            foreach (glob($integrationsPath.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [] as $dir) {
                $slug = basename($dir);
                if (Str::studly(str_replace('-', '_', $slug)) !== $integrationStudly) {
                    continue;
                }

                // Match disk layout from integrations:make: kebab-case dirs (hyphens), not snake+kebab (underscores).
                $groupDir = Str::kebab($groupStudly);
                $flowDir = Str::kebab($flowStudly);
                $file = $dir
                    .DIRECTORY_SEPARATOR.'groups'
                    .DIRECTORY_SEPARATOR.$groupDir
                    .DIRECTORY_SEPARATOR.'flows'
                    .DIRECTORY_SEPARATOR.$flowDir
                    .DIRECTORY_SEPARATOR.$shortClass.'.php';

                if (is_file($file)) {
                    require_once $file;
                }

                return;
            }
        });
    }
}
