<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeIntegrationGroupCommand extends Command
{
    protected $signature = 'integrations:make-group';

    protected $description = 'Create a group scaffold inside an existing integration.';

    public function handle(): int
    {
        $basePath = rtrim((string) config('flows.integrations_path', base_path('integrations')), DIRECTORY_SEPARATOR);

        $integrationSlug = $this->askKebabSlug('Integration slug (existing folder)');
        $integrationDir = $basePath.DIRECTORY_SEPARATOR.$integrationSlug;

        if (! File::isDirectory($integrationDir)) {
            $this->error("Integration [{$integrationSlug}] does not exist at [{$integrationDir}].");

            return self::FAILURE;
        }

        $groupSlug = $this->askKebabSlug('Group slug');
        $groupDir = $integrationDir.DIRECTORY_SEPARATOR.'groups'.DIRECTORY_SEPARATOR.$groupSlug;
        $groupConfigPath = $groupDir.DIRECTORY_SEPARATOR.'config.php';

        if (File::exists($groupConfigPath) && ! $this->confirm("Group config [{$groupConfigPath}] already exists. Overwrite?", false)) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists($groupDir.DIRECTORY_SEPARATOR.'flows');
        File::put($groupConfigPath, $this->groupConfigContents($groupSlug));

        $this->info("Group created: {$integrationSlug}/{$groupSlug}");

        return self::SUCCESS;
    }

    private function askKebabSlug(string $question): string
    {
        while (true) {
            $value = $this->ask($question);
            $value = is_string($value) ? trim($value) : '';

            if ($value === '') {
                $this->error('Value is required.');

                continue;
            }

            if (! preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $value)) {
                $this->error('Use kebab-case: lowercase letters, numbers, and hyphens.');

                continue;
            }

            return $value;
        }
    }

    private function groupConfigContents(string $groupSlug): string
    {
        $groupExport = var_export($groupSlug, true);

        return <<<PHP
<?php

return [
    'extra_config' => [
        'group' => {$groupExport},
    ],
];

PHP;
    }
}
